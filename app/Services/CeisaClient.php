<?php

namespace App\Services;

use App\Models\CeisaApiLog;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;

/**
 * HTTP Client wrapper untuk gateway CEISA 4.0.
 *
 * Otomatis:
 * - Memilih base URL sesuai gateway_mode (sandbox/production) + openapi_path v2.
 *   Sejak v2, semua service (manifes, pib, status, cnpibk, dll) pakai base
 *   unified: {gateway}/v2/openapi (lihat config('ceisa.openapi_path')).
 * - Menyisipkan DUA header auth (VERIFIED dari OpenAPI Portal):
 *     • `beacukai-api-key: <api_key>`  (APIKEY policy)
 *     • `Authorization: Bearer <token>` (OAuth2 policy)
 * - Auto-refresh Bearer token + retry sekali bila gateway return 401.
 * - Logging setiap request ke ceisa_api_logs.
 *
 * Catatan: format header sudah diverifikasi dari metadata OpenAPI Portal BC
 * (API "openapi-manifes"):
 *   apiKeyHeader = "beacukai-api-key", policies = [beacukai-api-key, oauth2].
 */
class CeisaClient
{
    public function __construct(
        protected CeisaCredentialService $credentials,
        protected CeisaOAuthService $oauth,
    ) {
    }

    /**
     * Guzzle instance untuk sebuah service.
     *
     * Sejak CEISA OpenAPI v2, base URL sudah unified (semua service pakai
     * {gateway}/v2/openapi). Parameter $service dipertahankan untuk
     * backward-compat tapi tidak lagi mengubah hasil (lihat
     * CeisaCredentialService::buildServiceBaseUrl()).
     *
     * @param string $service 'manifes' | 'pib' (ignored di v2). Default 'manifes'.
     */
    protected function client(string $service = 'manifes'): Client
    {
        $creds = $this->credentials->getCredentials();

        // ⚠️ TIDAK memakai 'base_uri' + relative path. Path endpoint CEISA
        // umumnya diawali '/' (mis. "/status") sehingga Guzzle (RFC 3986)
        // akan MENGGANTI seluruh base path → prefix "/v2/openapi" hilang.
        // URL absolut disusun di buildAbsoluteUrl() dan dipakai per-request.
        return new Client([
            'timeout' => (float) config('ceisa.http_timeout', 30),
            'connect_timeout' => (float) config('ceisa.connect_timeout', 10),
            'headers' => $this->buildHeaders($creds['api_key'] ?? ''),
            // Paksa HTTP/1.1 (gateway BC tidak advertise h2 meskipun TLS 1.3).
            'version' => 1.1,
            'curl' => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ],
        ]);
    }

    /**
     * Susun URL absolut: base service URL + path endpoint.
     *
     * Menggantikan pola base_uri + relative path Guzzle yang bermasalah saat
     * path diawali '/' (RFC 3986 mengganti base path). Ini menjamin prefix
     * "/v2/openapi" (atau path base lain) selalu dipertahankan.
     */
    protected function buildAbsoluteUrl(string $service, string $path): string
    {
        $baseUrl = rtrim($this->credentials->buildServiceBaseUrl($service), '/');

        // Bila path sudah absolut (skema http/https), pakai apa adanya.
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return $baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * Ping gateway untuk mengecek konektivitas (Fase 2 — test koneksi).
     *
     * Menggunakan http_errors=false sehingga response 4xx/5xx tidak dilempar
     * sebagai exception. Yang penting adalah apakah gateway memberi respons
     * HTTP apa pun (reachable) atau justru gagal total (connection/SSL error).
     *
     * @return array{reachable: bool, status: int, raw: string, error: ?string}
     */
    public function ping(?string $path = null): array
    {
        $path = $path ?: (string) config('ceisa.test_path', '/v2/openapi/status/ping-test');
        $start = microtime(true);
        $gateway = $this->credentials->getGatewayUrl();
        $absoluteUrl = $this->buildAbsoluteUrl('manifes', $path);

        try {
            $response = $this->client('manifes')->get($absoluteUrl, [
                RequestOptions::HTTP_ERRORS => false,
            ]);
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $code = $response->getStatusCode();
            $raw = (string) $response->getBody();

            CeisaApiLog::logOutbound(
                endpoint: $path,
                method: 'GET',
                request: ['ping' => true],
                response: ['status' => $code, 'raw' => $raw],
                code: $code,
                durationMs: $durationMs,
            );

            return [
                'reachable' => true,
                'status' => $code,
                'raw' => $raw,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            CeisaApiLog::logOutbound(
                endpoint: $path,
                method: 'GET',
                request: ['ping' => true],
                response: ['error' => $e->getMessage()],
                code: 0,
                durationMs: $durationMs,
                error: $e->getMessage(),
            );

            Log::error('CeisaClient ping failed', [
                'gateway' => $gateway,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'reachable' => false,
                'status' => 0,
                'raw' => '',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Header wajib (VERIFIED dari OpenAPI Portal).
     *
     * API Key dipasang via header `beacukai-api-key`. Header Authorization
     * (Bearer) ditambahkan per-request karena token bisa expire & di-refresh.
     */
    protected function buildHeaders(string $apiKey): array
    {
        $apiKeyHeader = (string) config('ceisa.auth.api_key_header', 'beacukai-api-key');

        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            $apiKeyHeader => $apiKey,
        ];
    }

    /**
     * POST request dengan auto-logging + auto-auth-retry.
     *
     * @param string $service 'manifes' | 'pib'.
     * @param array  $query  Query parameters (optional, v2 POST /document butuh
     *                       ?isFinal=&isRevision=).
     * @return array{status: int, body: array, raw: string}
     */
    public function post(string $path, array $payload, string $service = 'manifes', array $query = []): array
    {
        return $this->request('POST', $path, $service, $payload, $query);
    }

    /**
     * POST multipart (file upload) ke gateway BC.
     *
     * Berbeda dari post() yang kirim JSON, method ini kirim multipart/form-data
     * untuk upload file binary (invoice, BL, packing list, DOKAP/NPD).
     *
     * @param  string $service  'manifes' | 'pib'.
     * @param  array  $files    Format: [['name' => 'file', 'contents' => $binaryData, 'filename' => 'invoice.pdf'], ...]
     * @param  array  $formFields Field form tambahan (non-file), mis. ['nomorAju' => '...'].
     * @param  array  $query    Query string params.
     * @return array{status: int, body: array, raw: string}
     */
    public function postMultipart(
        string $path,
        array $files,
        array $formFields = [],
        string $service = 'manifes',
        array $query = [],
    ): array {
        $start = microtime(true);
        $logPayload = ['method' => 'POST(multipart)', 'path' => $path, 'service' => $service,
            'files' => array_map(fn ($f) => $f['filename'] ?? '?', $files), 'fields' => array_keys($formFields), 'query' => $query];

        if ($this->isMockEnabled()) {
            $mockBody = [
                'status' => 'OK',
                'message' => '[MOCK] File uploaded (CEISA_MOCK_ENABLED=true). No real gateway call.',
                'uploadedFiles' => array_map(fn ($f) => $f['filename'] ?? '?', $files),
                'receivedAt' => now()->toIso8601String(),
            ];
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            CeisaApiLog::logOutbound($path, 'POST', $logPayload, $mockBody, 200, $durationMs);

            return ['status' => 200, 'body' => $mockBody, 'raw' => json_encode($mockBody)];
        }

        $endpoint = $path;

        try {
            $token = $this->resolveBearerToken();
            $headers = [
                'Accept' => 'application/json',
                'beacukai-api-key' => $this->credentials->getApiKey(),
            ];
            if (!empty($token)) {
                $headers['Authorization'] = 'Bearer ' . $token;
            }

            $absoluteUrl = $this->buildAbsoluteUrl($service, $path);

            // Build multipart array for Guzzle.
            $multipart = [];
            foreach ($formFields as $name => $value) {
                $multipart[] = ['name' => $name, 'contents' => (string) $value];
            }
            foreach ($files as $file) {
                $multipart[] = [
                    'name' => $file['name'] ?? 'file',
                    'contents' => $file['contents'],
                    'filename' => $file['filename'] ?? 'upload.bin',
                    'headers' => ['Content-Type' => $file['contentType'] ?? 'application/octet-stream'],
                ];
            }

            if (!empty($query)) {
                $absoluteUrl .= (str_contains($absoluteUrl, '?') ? '&' : '?') . http_build_query($query);
            }

            $client = new Client([
                'timeout' => (float) config('ceisa.http_timeout', 30),
                'connect_timeout' => (float) config('ceisa.connect_timeout', 10),
                'version' => 1.1,
                'curl' => [CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1],
            ]);

            $response = $client->request('POST', $absoluteUrl, [
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::HEADERS => $headers,
                RequestOptions::MULTIPART => $multipart,
            ]);

            $code = $response->getStatusCode();
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $raw = (string) $response->getBody();

            $body = json_decode($raw, true) ?? ['raw' => $raw];

            CeisaApiLog::logOutbound($endpoint, 'POST', $logPayload, $body, $code, $durationMs);

            return ['status' => $code, 'body' => is_array($body) ? $body : ['raw' => $body], 'raw' => $raw];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            CeisaApiLog::logOutbound($endpoint, 'POST', $logPayload, ['error' => $e->getMessage()], 0, $durationMs, error: $e->getMessage());
            Log::error('CeisaClient postMultipart failed', ['path' => $path, 'error' => $e->getMessage()]);

            return ['status' => 0, 'body' => ['error' => $e->getMessage()], 'raw' => ''];
        }
    }

    /**
     * GET request dengan auto-logging + auto-auth-retry.
     *
     * @param string $service 'manifes' | 'pib'.
     */
    public function get(string $path, array $query = [], string $service = 'manifes'): array
    {
        return $this->request('GET', $path, $service, [], $query);
    }

    /**
     * DELETE request dengan auto-logging + auto-auth-retry.
     *
     * @param string $service 'manifes' | 'pib'.
     */
    public function delete(string $path, array $query = [], string $service = 'manifes'): array
    {
        return $this->request('DELETE', $path, $service, [], $query);
    }

    /**
     * PUT request dengan auto-logging + auto-auth-retry.
     *
     * Dipakai oleh openapi-cukai untuk update Mesin (PUT /h2h/mesin/{id}).
     *
     * @param string $service 'manifes' | 'pib' | 'cukai'.
     */
    public function put(string $path, array $payload = [], string $service = 'manifes', array $query = []): array
    {
        return $this->request('PUT', $path, $service, $payload, $query);
    }

    /**
     * GET raw (binary) response — untuk download PDF/image dari gateway BC.
     *
     * Berbeda dari get(): tidak JSON-decode body, mengembalikan raw bytes.
     * Auto-logging + auto-auth-retry tetap aktif.
     *
     * @return array{
     *     status: int,
     *     content: string,    // raw binary
     *     content_type: string,
     *     endpoint: string
     * }
     */
    public function getRaw(string $path, array $query = [], string $service = 'manifes'): array
    {
        $endpoint = $path;
        $start = microtime(true);
        $logPayload = ['method' => 'GET', 'path' => $path, 'service' => $service, 'query' => $query, 'binary' => true];

        // --- Mock mode: synthetic PDF kecil agar end-to-end testable ---
        if ($this->isMockEnabled()) {
            $mockPdf = $this->buildMockPdf($path);
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            CeisaApiLog::logOutbound(
                endpoint: $endpoint,
                method: 'GET',
                request: $logPayload,
                response: ['binary' => true, 'size' => strlen($mockPdf)],
                code: 200,
                durationMs: $durationMs,
            );

            return [
                'status'       => 200,
                'content'      => $mockPdf,
                'content_type' => 'application/pdf',
                'endpoint'     => $endpoint,
            ];
        }

        try {
            $response = $this->sendWithBearer('GET', $path, $service, [], $query);
            $code = $response->getStatusCode();

            // Pass-through mode: 401 retry tidak diperlukan di backend —
            // frontend interceptor akan handle refresh & retry request.

            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $content = (string) $response->getBody();
            $contentType = $response->getHeaderLine('Content-Type');

            CeisaApiLog::logOutbound(
                endpoint: $endpoint,
                method: 'GET',
                request: $logPayload,
                response: ['binary' => true, 'size' => strlen($content), 'content_type' => $contentType],
                code: $code,
                durationMs: $durationMs,
            );

            return [
                'status'       => $code,
                'content'      => $content,
                'content_type' => $contentType,
                'endpoint'     => $endpoint,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            CeisaApiLog::logOutbound(
                endpoint: $endpoint,
                method: 'GET',
                request: $logPayload,
                response: ['error' => $e->getMessage()],
                code: 0,
                durationMs: $durationMs,
                error: $e->getMessage(),
            );

            Log::error('CeisaClient getRaw failed', [
                'path'  => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'status'       => 0,
                'content'      => '',
                'content_type' => '',
                'endpoint'     => $endpoint,
            ];
        }
    }

    /**
     * Apakah mock mode aktif (CEISA_MOCK_ENABLED=true)?
     */
    public function isMockEnabled(): bool
    {
        return (bool) config('ceisa.mock_enabled', false);
    }

    /**
     * Generic request dengan logging ke ceisa_api_logs + 401 auto-retry.
     *
     * Catatan: gunakan http_errors=false agar response 4xx/5xx dari gateway
     * tidak dilempar sebagai exception — pemanggil yang menentukan tindakan
     * berdasarkan status code. Ini juga mencegah runtime exception saat
     * QUEUE_CONNECTION=sync.
     */
    protected function request(string $method, string $path, string $service, array $body = [], array $query = []): array
    {
        $endpoint = $path;
        $method = strtoupper($method);
        $start = microtime(true);

        $logPayload = ['method' => $method, 'path' => $path, 'service' => $service, 'body' => $body, 'query' => $query];

        // --- Mock mode: return synthetic response tanpa memanggil gateway ---
        if ($this->isMockEnabled()) {
            $mockBody = $this->buildMockResponse($method, $path, $body);
            $mockRaw = json_encode($mockBody);
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            CeisaApiLog::logOutbound(
                endpoint: $endpoint,
                method: $method,
                request: $logPayload,
                response: $mockBody,
                code: 200,
                durationMs: $durationMs,
            );

            Log::info('CeisaClient mock response', [
                'path' => $path,
                'method' => $method,
                'service' => $service,
            ]);

            return [
                'status' => 200,
                'body' => $mockBody,
                'raw' => $mockRaw,
            ];
        }

        try {
            $response = $this->sendWithBearer($method, $path, $service, $body, $query);
            $code = $response->getStatusCode();

            // Pass-through mode: 401 retry tidak diperlukan di backend —
            // frontend interceptor akan handle refresh & retry request.

            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $raw = (string) $response->getBody();
            $decoded = json_decode($raw, true) ?? [];

            CeisaApiLog::logOutbound(
                endpoint: $endpoint,
                method: $method,
                request: $logPayload,
                response: is_array($decoded) ? $decoded : ['raw' => $raw],
                code: $code,
                durationMs: $durationMs,
            );

            return [
                'status' => $code,
                'body' => is_array($decoded) ? $decoded : ['raw' => $raw],
                'raw' => $raw,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $code = method_exists($e, 'getCode') ? (int) $e->getCode() : 0;
            $code = $code > 0 ? $code : 500;

            CeisaApiLog::logOutbound(
                endpoint: $endpoint,
                method: $method,
                request: $logPayload,
                response: ['error' => $e->getMessage()],
                code: $code,
                durationMs: $durationMs,
                error: $e->getMessage(),
            );

            Log::error('CeisaClient request failed', [
                'path' => $path,
                'method' => $method,
                'service' => $service,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Eksekusi request Guzzle dengan Bearer token.
     *
     * Token di-resolve via resolveBearerToken() yang memakai pola pass-through:
     * frontend (mobile/web) simpan token BC di SecureStore lalu kirim via
     * header Authorization ke backend SAGANSA. Backend meneruskan token
     * tersebut ke gateway BC — tidak menyimpan username/password BC.
     */
    protected function sendWithBearer(
        string $method,
        string $path,
        string $service,
        array $body,
        array $query,
        bool $forceRefresh = false,
    ): \Psr\Http\Message\ResponseInterface {
        $options = [RequestOptions::HTTP_ERRORS => false];
        if (!empty($query)) {
            $options[RequestOptions::QUERY] = $query;
        }
        if (!empty($body)) {
            $options[RequestOptions::JSON] = $body;
        }

        $token = $this->resolveBearerToken($forceRefresh);
        if (!empty($token)) {
            $options[RequestOptions::HEADERS]['Authorization'] = 'Bearer ' . $token;
        }

        // Pakai URL absolut (bukan base_uri + relative) agar prefix base path
        // (mis. "/v2/openapi") tidak hilang saat path diawali '/'.
        $absoluteUrl = $this->buildAbsoluteUrl($service, $path);

        return $this->client($service)->request($method, $absoluteUrl, $options);
    }

    /**
     * Resolve Bearer token untuk dikirim ke gateway BC (pass-through mode).
     *
     * Prioritas sumber token:
     *  1. Incoming request Authorization header (pass-through dari frontend)
     *     — frontend login BC → dapat token → kirim ke backend via
     *       axios interceptor → backend teruskan ke BC. Aman: backend
     *       tidak menyimpan kredensial BC.
     *  2. Fallback: null (gateway akan balas 401, frontend interceptor
     *     akan trigger refresh/re-login).
     *
     * @param  bool $forceRefresh Diabaikan di pass-through mode (frontend
     *                            yang handle refresh via interceptor).
     * @return string|null Token Bearer atau null.
     */
    protected function resolveBearerToken(bool $forceRefresh = false): ?string
    {
        // Baca Authorization header dari request yang sedang diproses.
        // Laravel request() helper aman dipanggil dalam HTTP context.
        $header = request()?->headers->get('Authorization');

        if ($header && preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Build synthetic response untuk mock mode.
     *
     * Format mock mengikuti kontrak OpenAPI placeholder:
     * { responseId, nomorPendaftaran, status }
     */
    protected function buildMockResponse(string $method, string $path, array $body): array
    {
        $now = now();

        // v2 status endpoint: GET /status/{nomorAju}
        // Response v2 (lihat OpenAPI v2 /status/{nomorAju}): { dataStatus:[], dataRespon:[] }
        if (preg_match('#/status/\{?[^/]+\}?$#', $path) || str_contains($path, '/status/')) {
            $nomorAju = $body['nomorAju'] ?? basename($path);

            return [
                'dataStatus' => [
                    [
                        'nomorAju'     => $nomorAju,
                        'kodeStatus'   => '01',
                        'nomorDaftar'  => sprintf('%06d', random_int(1, 999999)),
                        'tanggalDaftar'=> $now->toDateString(),
                        'waktuStatus'  => $now->toDateTimeString(),
                        'keterangan'   => '[MOCK] Dokumen dalam proses',
                    ],
                ],
                'dataRespon' => [],
            ];
        }

        // v2 POST /document — submission response: { status, message, idHeader }
        if (str_contains($path, '/document')) {
            return [
                'status'   => 'OK',
                'message'  => '[MOCK] Sukses, Data Berhasil Ditambahkan',
                'idHeader' => 'MOCK-' . strtoupper(sha1((string) $now->timestamp)),
            ];
        }

        $aju = $body['nomorAju'] ?? ($body['header']['nomorAju'] ?? ('MOCK-' . $now->timestamp));

        return [
            'responseId' => 'MOCK-' . strtoupper(sha1($aju . $now->timestamp)),
            'nomorPendaftaran' => sprintf('%06d', random_int(1, 999999)),
            'status' => 'AJU',
            'message' => '[MOCK] Submission accepted. CEISA_MOCK_ENABLED=true, no real gateway call made.',
            'received_at' => $now->toIso8601String(),
        ];
    }

    /**
     * Build minimal valid PDF (mock) untuk testing download tanpa gateway BC.
     *
     * PDF ini berisi teks "[MOCK] SAGANSA CEISA" + path yang diminta. Tidak
     * dienkripsi, struktur valid sesuai spec PDF 1.4 agar bisa dibuka di
     * viewer manapun (untuk verifikasi flow end-to-end).
     */
    protected function buildMockPdf(string $path): string
    {
        $now = now()->toDateTimeString();
        $text = "[MOCK] SAGANSA CEISA\n\nEndpoint: {$path}\nGenerated: {$now}\n\nThis is a synthetic PDF generated by CEISA_MOCK_ENABLED=true.";

        // PDF 1.4 minimal structure. xref offset dihitung manual.
        $header = "%PDF-1.4\n";
        $content = "BT /F1 12 Tf 50 750 Td (" . str_replace(["\n", '(', ')'], [') Tj 0 -16 Td (', '\\(', '\\)'], $text) . ") Tj ET";
        $stream = "<<\n/Length " . strlen($content) . "\n>>\nstream\n" . $content . "\nendstream";

        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
        $obj4 = "4 0 obj\n" . $stream . "\nendobj\n";
        $obj5 = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";

        $body = $obj1 . $obj2 . $obj3 . $obj4 . $obj5;
        $xrefOffset = strlen($header) + strlen($body);
        $xref = "xref\n0 6\n0000000000 65535 f \n";
        $offsets = [strlen($header), strlen($header) + strlen($obj1), strlen($header) + strlen($obj1) + strlen($obj2)];
        // Offset dihitung kumulatif untuk setiap objek.
        $cum = strlen($header);
        $xref = "xref\n0 6\n0000000000 65535 f \n";
        foreach ([$obj1, $obj2, $obj3, $obj4, $obj5] as $obj) {
            $cum += strlen($obj);
            $xref .= sprintf("%010d 00000 n \n", $cum - strlen($obj));
        }
        $xrefOffset = strlen($header);
        $trailer = "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        return $header . $body . $xref . $trailer;
    }
}