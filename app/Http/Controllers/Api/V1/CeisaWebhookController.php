<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWebhookJob;
use App\Models\CeisaApiLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menerima callback status PIB dari CEISA 4.0.
 *
 * NFR: respon 200 OK dalam < 2 detik → proses berat di ProcessWebhookJob (async).
 * Implementasi signature verification & parsing lengkap: Fase 4.
 */
class CeisaWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $raw = $request->getContent();

        // 1) Optional signature verification (shared secret) — cek OpenAPI.
        if ($this->signatureInvalid($request, $raw)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // 2) Log raw inbound payload (ceisa_api_logs, direction=inbound)
        CeisaApiLog::create([
            'direction' => 'inbound',
            'endpoint' => $request->fullUrl(),
            'method' => $request->method(),
            'request_payload' => $payload,
            'response_payload' => ['ack' => true],
            'response_code' => 200,
        ]);

        // 3) Dispatch async processing
        ProcessWebhookJob::dispatch($payload);

        // 4) Acknowledge immediately (< 2s) — per NFR
        return response()->json(['received' => true], 200);
    }

    /**
     * Verifikasi signature HMAC (opsional). Format tergantung OpenAPI CEISA.
     */
    private function signatureInvalid(Request $request, string $raw): bool
    {
        $secret = config('ceisa.webhook_secret');
        if (empty($secret)) {
            // Jika secret tidak dikonfigurasi, lewati verifikasi (setup sandbox).
            return false;
        }

        $provided = $request->headers->get('X-CEISA-Signature');
        $expected = hash_hmac('sha256', $raw, $secret);

        return !hash_equals($expected, (string) $provided);
    }
}