<?php

namespace App\Services;

use App\Models\CeisaApiLog;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

/**
 * Fitur 1/2 — HTTP Client wrapper untuk gateway CEISA 4.0.
 *
 * Otomatis:
 * - Memilih base URL sesuai gateway_mode (sandbox/production)
 * - Menyisipkan header Application ID + API Key
 * - Logging setiap request ke ceisa_api_logs
 *
 * Catatan: format header & signature WAJIB diverifikasi via OpenAPI Portal.
 */
class CeisaClient
{
    public function __construct(protected CeisaCredentialService $credentials)
    {
    }

    /**
     * Guzzle instance dengan base_uri & default headers.
     *
     * Catatan: gateway CEISA hanya mendukung HTTP/1.1 (lihat ALPN saat
     * TLS handshake), sehingga kita paksa 'curl' handler + HTTP/1.1 agar
     * Guzzle tidak mencoba upgrade ke HTTP/2 yang dapat menyebabkan
     * "unexpected eof while reading" (cURL error 56).
     */
    protected function client(): Client
    {
        $creds = $this->credentials->getCredentials();

        return new Client([
            'base_uri' => $this->credentials->getGatewayUrl(),
            'timeout' => (float) config('ceisa.http_timeout', 30),
            'connect_timeout' => (float) config('ceisa.connect_timeout', 10),
            'headers' => $this->buildHeaders($creds['application_id'], $creds['api_key']),
            // Paksa HTTP/1.1 (gateway BC tidak advertise h2 meskipun TLS 1.3).
            'version' => 1.1,
            'curl' => [
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ],
        ]);
    }

    /**
     * Ping gateway untuk mengecek konektivitas (Fase 2 — test koneksi).
     *
     * Berbeda dengan get()/post(): pakai http_errors=false sehingga response
     * 4xx/5xx tidak dilempar sebagai exception. Yang penting bagi pemanggil
     * adalah apakah gateway memberi respons HTTP apa pun (reachable) atau
     * justru gagal total (connection/SSL error).
     *
     * @return array{reachable: bool, status: int, raw: string, error: ?string}
     */
    public function ping(?string $path = null): array
    {
        $path = $path ?: (string) config('ceisa.test_path', '/v1/pib/submit');
        $start = microtime(true);
        $gateway = $this->credentials->getGatewayUrl();

        try {
            $response = $this->client()->get($path, [
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
     * Header wajib (verifikasi format di OpenAPI Portal).
     */
    protected function buildHeaders(string $applicationId, string $apiKey): array
    {
        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            // Format header bisa berupa 'Application-Id' / 'API-Key' atau lain — sesuaikan dengan OpenAPI.
            'Application-Id' => $applicationId,
            'API-Key' => $apiKey,
        ];
    }

    /**
     * POST request dengan auto-logging.
     *
     * @return array{status: int, body: array, raw: string}
     */
    public function post(string $path, array $payload): array
    {
        return $this->request('POST', $path, $payload);
    }

    /**
     * GET request dengan auto-logging.
     */
    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, [], $query);
    }

    /**
     * Generic request dengan logging ke ceisa_api_logs.
     */
    protected function request(string $method, string $path, array $body = [], array $query = []): array
    {
        $endpoint = $path;
        $method = strtoupper($method);
        $start = microtime(true);

        $logPayload = ['method' => $method, 'path' => $path, 'body' => $body, 'query' => $query];

        try {
            $options = [];
            if (!empty($query)) {
                $options[RequestOptions::QUERY] = $query;
            }
            if (!empty($body)) {
                $options[RequestOptions::JSON] = $body;
            }

            $response = $this->client()->request($method, $path, $options);
            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $raw = (string) $response->getBody();
            $decoded = json_decode($raw, true) ?? [];

            CeisaApiLog::logOutbound(
                endpoint: $endpoint,
                method: $method,
                request: $logPayload,
                response: is_array($decoded) ? $decoded : ['raw' => $raw],
                code: $response->getStatusCode(),
                durationMs: $durationMs,
            );

            return [
                'status' => $response->getStatusCode(),
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
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}