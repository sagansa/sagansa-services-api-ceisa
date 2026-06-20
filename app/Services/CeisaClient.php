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
     */
    protected function client(): Client
    {
        $creds = $this->credentials->getCredentials();

        return new Client([
            'base_uri' => $this->credentials->getGatewayUrl(),
            'timeout' => (float) config('ceisa.http_timeout', 30),
            'headers' => $this->buildHeaders($creds['application_id'], $creds['api_key']),
        ]);
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