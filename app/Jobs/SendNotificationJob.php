<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\PibDocument;
use App\Notifications\NotificationChannelInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fase 5 — Per-channel notification job with retry & fail-safe.
 *
 * NFR Fail-safe: jika Telegram/FCM gagal, Email wajib tetap dikirim.
 */
class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $pibDocumentId,
        public string $channel,
        public string $event,
        public string $urgency,
        public array $data,
    ) {
    }

    public function handle(): void
    {
        $pib = PibDocument::find($this->pibDocumentId);

        // Buat log record awal (queued)
        $log = NotificationLog::create([
            'pib_document_id' => $this->pibDocumentId,
            'channel' => $this->channel,
            'event' => $this->event,
            'payload' => $this->data,
            'status' => 'queued',
            'attempts' => 1,
        ]);

        try {
            $channelImpl = $this->resolveChannel($this->channel);

            if (!$channelImpl) {
                throw new \RuntimeException("Unknown channel: {$this->channel}");
            }

            $ok = $channelImpl->send($pib, $this->event, $this->data);

            $log->update([
                'status' => $ok ? 'sent' : 'failed',
                'sent_at' => $ok ? now() : null,
            ]);

            if (!$ok) {
                throw new \RuntimeException("Channel {$this->channel} returned false");
            }
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::error('SendNotificationJob failed', [
                'channel' => $this->channel,
                'pib' => $this->pibDocumentId,
                'error' => $e->getMessage(),
            ]);

            // FAIL-SAFE: jika ini bukan email, pastikan email tetap dikirim
            if ($this->channel !== 'email') {
                $this->ensureEmailSent($pib);
            }

            throw $e; // re-throw agar retry queue berjalan
        }
    }

    /**
     * Resolve channel implementation dari container.
     */
    protected function resolveChannel(string $channel): ?NotificationChannelInterface
    {
        $map = [
            'fcm' => \App\Notifications\Channels\FcmChannel::class,
            'telegram' => \App\Notifications\Channels\TelegramChannel::class,
            'email' => \App\Notifications\Channels\EmailChannel::class,
        ];

        $class = $map[$channel] ?? null;

        return $class ? app($class) : null;
    }

    /**
     * Fail-safe: kirim email backup bila channel lain gagal (NFR).
     */
    protected function ensureEmailSent(?PibDocument $pib): void
    {
        if (!$pib || $this->urgency !== 'urgent') {
            return;
        }

        try {
            $emailChannel = app(\App\Notifications\Channels\EmailChannel::class);
            $emailChannel->send($pib, $this->event, $this->data);
        } catch (\Throwable $e) {
            Log::critical('Fail-safe email also failed', [
                'pib' => $this->pibDocumentId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}