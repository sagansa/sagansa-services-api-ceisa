<?php

namespace App\Notifications\Channels;

use App\Models\FcmDeviceToken;
use App\Models\NotificationSetting;
use App\Models\PibDocument;
use App\Notifications\NotificationChannelInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fase 5.1 — Firebase Cloud Messaging (FCM) channel.
 *
 * Mengirim push notification ke HP (Android/iOS) via FCM HTTP v1 API.
 * NFR: payload maks 4KB.
 */
class FcmChannel implements NotificationChannelInterface
{
    public function channel(): string
    {
        return 'fcm';
    }

    public function send(PibDocument $doc, string $event, array $data): bool
    {
        $serverKey = config('services.fcm.server_key', env('FCM_SERVER_KEY'));
        if (empty($serverKey)) {
            Log::info('FcmChannel: server key not configured, skipping');

            return false;
        }

        $tokens = $this->resolveTokens();
        if ($tokens->isEmpty()) {
            Log::info('FcmChannel: no device tokens registered');

            return false;
        }

        $payload = $this->buildPayload($doc, $event, $data);

        try {
            // Legacy FCM HTTP API (mudah untuk MVP; production sebaiknya v1 + OAuth).
            $response = Http::withHeaders([
                'Authorization' => "key={$serverKey}",
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'registration_ids' => $tokens->pluck('device_token')->all(),
                'notification' => $payload,
                'data' => [
                    'pib_id' => $doc->id,
                    'aju_number' => $doc->aju_number,
                    'event' => $event,
                ],
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('FcmChannel send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Ambil semua device token aktif.
     */
    protected function resolveTokens()
    {
        return FcmDeviceToken::where('is_active', true)->limit(1000)->get();
    }

    /**
     * Build notification payload (max 4KB — NFR).
     */
    protected function buildPayload(PibDocument $doc, string $event, array $data): array
    {
        $title = $doc->is_underprice
            ? '⚠️ UNDERPRICE Terdeteksi!'
            : "PIB {$event}";

        $body = $doc->is_underprice
            ? "PIB {$doc->aju_number} terkena NOTUL dengan koreksi nilai pabean."
            : "PIB {$doc->aju_number} status: {$doc->status}";

        // Truncate body agar total payload < 4KB
        $body = mb_substr($body, 0, 500);

        return [
            'title' => mb_substr($title, 0, 100),
            'body' => $body,
            'sound' => 'default',
            'android' => ['priority' => 'high'],
        ];
    }
}