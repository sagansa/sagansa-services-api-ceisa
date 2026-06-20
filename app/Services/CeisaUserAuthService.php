<?php

namespace App\Services;

use App\Models\CeisaApiLog;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;

/**
 * CEISA 4.0 H2H User Authentication Service.
 *
 * Mengimplementasikan API "openapi-auth" (versi 1) dari portal Bea Cukai:
 *
 *   Base URL: {gateway}/v1/openapi-auth
 *   - POST /user/login        → login user H2H, mendapat access token user
 *   - POST /user/update-token → refresh/perpanjang token user yang masih aktif
 *
 * Berbeda dengan CeisaOAuthService (Client Credentials Grant untuk Bearer
 * gateway), service ini menangani login USER (username/password H2H BC).
 * Token user umumnya dipakai untuk operasi yang terikat identitas user BC
 * (mis. submit dokumen atas nama user tertentu).
 *
 * Token user di-cache pada atribut dinamis credential aktif:
 *   - user_access_token     (encrypted via cast)
 *   - user_token_expires_at
 *
 * Catatan: response BC tidak memiliki OpenAPI schema detail (semua "default"),
 * sehingga parsing dilakukan defensif (permitted keys: access_token, token,
 * jwt, expires_in, expires_at, token_type, refresh_token).
 *
 * Referensi: doc/json/Export_openapi-auth-v2.json,
 *            doc/json/Export_openapi-auth-update-token-v2.json
 */
class CeisaUserAuthService
{
    public function __construct(protected CeisaCredentialService $credentials)
    {
    }

    /**
     * Login user H2H ke gateway CEISA.
     *
     * @param array{username?: string, password?: string, token?: string} $payload
     * @return array{status: int, body: array, raw: string}
     */
    public function login(array $payload): array
    {
        $endpoint = (string) config('ceisa.auth_endpoints.user_login', '/user/login');
        $body = array_filter([
            'username' => $payload['username'] ?? null,
            'password' => $payload['password'] ?? null,
            'token' => $payload['token'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->call('POST', $endpoint, $body);
    }

    /**
     * Update / refresh token user H2H.
     *
     * @param array{token?: string, refresh_token?: string} $payload
     * @return array{status: int, body: array, raw: string}
     */
    public function updateToken(array $payload = []): array
    {
        $endpoint = (string) config('ceisa.auth_endpoints.user_update_token', '/user/update-token');
        $body = array_filter([
            'token' => $payload['token'] ?? null,
            'refresh_token' => $payload['refresh_token'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->call('POST', $endpoint, $body);
    }

    /**
     * Eksekusi request ke openapi-auth dengan logging + header gateway.
     *
     * Header mengikuti konfigurasi global (API Key + Bearer gateway via OAuth2).
     * Endpoint autentikasi user TIDAK butuh Bearer user — ia justru yang
     * mengeluarkannya.
     *
     * @return array{status: int, body: array, raw: string}
     */
    protected function call(string $method, string $path, array $body = []): array
    {
        $method = strtoupper($method);
        $baseUrl = $this->buildAuthBaseUrl();
        $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        $start = microtime(true);

        try {
            $client = new Client([
                'base_uri' => $baseUrl,
                'timeout' => (float) config('ceisa.http_timeout', 30),
                'connect_timeout' => (float) config('ceisa.connect_timeout', 10),
                'headers' => $this->buildHeaders(),
                'version' => 1.1,
                'curl' => [CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1],
            ]);

            $options = [RequestOptions::HTTP_ERRORS => false];
            if (!empty($body)) {
                $options[RequestOptions::JSON] = $body;
            }

            $response = $client->request($method, $path, $options);
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $code = $response->getStatusCode();
            $raw = (string) $response->getBody();
            $decoded = json_decode($raw, true) ?? [];

            CeisaApiLog::logOutbound(
                endpoint: $fullUrl,
                method: $method,
                request: $this->sanitizeForLog($body),
                response: is_array($decoded) ? $this->sanitizeForLog($decoded) : ['raw' => $raw],
                code: $code,
                durationMs: $durationMs,
                error: $code >= 400 ? "Auth request failed: HTTP {$code}" : null,
            );

            return [
                'status' => $code,
                'body' => is_array($decoded) ? $decoded : ['raw' => $raw],
                'raw' => $raw,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            CeisaApiLog::logOutbound(
                endpoint: $fullUrl,
                method: $method,
                request: $this->sanitizeForLog($body),
                response: ['error' => $e->getMessage()],
                code: 0,
                durationMs: $durationMs,
                error: $e->getMessage(),
            );

            Log::error('CeisaUserAuthService: exception', [
                'url' => $fullUrl,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 0,
                'body' => ['error' => $e->getMessage()],
                'raw' => '',
            ];
        }
    }

    /**
     * Base URL untuk openapi-auth: {gateway}/v1/openapi-auth.
     */
    protected function buildAuthBaseUrl(): string
    {
        $gateway = rtrim($this->credentials->getGatewayUrl(), '/');
        $authPath = (string) config('ceisa.openapi_auth_path', '/v1/openapi-auth');

        return $gateway . $authPath;
    }

    /**
     * Header gateway (API Key + Bearer gateway via OAuth2).
     *
     * Token gateway (bukan token user) dipakai agar bisa mengakses endpoint
     * openapi-auth. Token user TIDAK disertakan di sini.
     */
    protected function buildHeaders(): array
    {
        $creds = $this->credentials->getCredentials();
        $apiKeyHeader = (string) config('ceisa.auth.api_key_header', 'beacukai-api-key');

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            $apiKeyHeader => $creds['api_key'] ?? '',
        ];

        // Tambahkan Bearer gateway (client credentials) bila tersedia.
        $gatewayToken = app(CeisaOAuthService::class)->getAccessToken();
        if (!empty($gatewayToken)) {
            $headers['Authorization'] = 'Bearer ' . $gatewayToken;
        }

        return $headers;
    }

    /**
     * Hilangkan field sensitif (password) sebelum ditulis ke log.
     */
    protected function sanitizeForLog(array $data): array
    {
        if (isset($data['password'])) {
            $data['password'] = '***';
        }
        if (isset($data['client_secret'])) {
            $data['client_secret'] = '***';
        }

        return $data;
    }
}