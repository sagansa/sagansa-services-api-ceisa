<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Base class untuk service yang berinteraksi dengan gateway CEISA 4.0.
 *
 * Menyediakan helper reusable:
 *  - endpoint(): resolve config key → path + substitute placeholder.
 *  - wrapProxy(): bungkus response CeisaClient ke format standar.
 *  - logError(): logging konsisten dengan konteks.
 *
 * Tujuan: konsistensi antar service (Status, Manifes, Respon, Gate, dll)
 * sehingga controller cukup panggil satu pattern tanpa duplikasi boilerplate.
 */
abstract class CeisaBaseService
{
    public function __construct(protected CeisaClient $client)
    {
    }

    /**
     * Resolve endpoint dari config('ceisa.endpoints.{key}') + substitute
     * placeholder {param} dengan nilai sebenarnya.
     *
     * Contoh:
     *   $this->endpoint('status_by_aju', ['nomorAju' => '123'])
     *   → "/status/123"
     *
     * @param  string  $key    Config key di config/ceisa.php → endpoints.*
     * @param  array   $params Key-value pair untuk placeholder substitution.
     * @return string          Resolved path (relative ke {gateway}/v2/openapi).
     */
    protected function endpoint(string $key, array $params = []): string
    {
        $path = (string) config("ceisa.endpoints.{$key}", "/{$key}");

        foreach ($params as $k => $v) {
            $path = str_replace('{' . $k . '}', (string) $v, $path);
        }

        return $path;
    }

    /**
     * Bungkus hasil CeisaClient (GET/POST) ke format array standar.
     *
     * @param  array  $response Response dari CeisaClient->get/post (status, body, raw).
     * @param  string $context  Label konteks untuk logging (mis. "getByNomorAju").
     * @return array{
     *     success: bool,
     *     status_code: int,
     *     data: array,
     *     raw: array|string,
     *     error: ?string
     * }
     */
    protected function wrapProxy(array $response, string $context = ''): array
    {
        $status = $response['status'] ?? 0;
        $body = $response['body'] ?? [];
        $success = $status >= 200 && $status < 300;

        return [
            'success'     => $success,
            'status_code' => $status,
            'data'        => is_array($body) ? $body : ['raw' => $body],
            'raw'         => $response['raw'] ?? '',
            'error'       => $success ? null : ($body['message'] ?? $body['Exception'] ?? "HTTP {$status}"),
        ];
    }

    /**
     * Logging error dengan konteks service. Format konsisten agar mudah
     * di-search di storage/logs/laravel.log.
     */
    protected function logError(string $context, array $data): void
    {
        Log::error(static::class . '::' . $context, $data);
    }
}
