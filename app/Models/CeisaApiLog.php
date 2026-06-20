<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Log H2H API (outbound & inbound).
 */
class CeisaApiLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'direction',
        'endpoint',
        'method',
        'request_payload',
        'response_payload',
        'response_code',
        'duration_ms',
        'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'response_code' => 'integer',
        'duration_ms' => 'integer',
    ];

    public static function logOutbound(string $endpoint, string $method, array $request, array $response, int $code, int $durationMs, ?string $error = null): self
    {
        return self::create([
            'direction' => 'outbound',
            'endpoint' => $endpoint,
            'method' => $method,
            'request_payload' => $request,
            'response_payload' => $response,
            'response_code' => $code,
            'duration_ms' => $durationMs,
            'error_message' => $error,
        ]);
    }
}