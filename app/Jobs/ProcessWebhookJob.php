<?php

namespace App\Jobs;

use App\Models\CeisaStatusHistory;
use App\Models\PibDocument;
use App\Services\NotificationDispatcher;
use App\Services\WebhookPayloadParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fase 4 — Async processing webhook CEISA.
 *
 * Parse payload, update status PIB, klasifikasi urgensi, lalu dispatch notifikasi.
 */
class ProcessWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public array $payload)
    {
    }

    public function handle(WebhookPayloadParser $parser, NotificationDispatcher $dispatcher): void
    {
        try {
            $parsed = $parser->parse($this->payload);

            // Cari / buat PibDocument by aju_number
            $pib = $this->resolvePibDocument($parsed);

            // Update status + last_webhook_at
            if ($parsed->status) {
                $pib->update([
                    'status' => $parsed->status,
                    'last_webhook_at' => now(),
                    'registration_number' => $parsed->registrationNumber ?? $pib->registration_number,
                ]);
            }

            // Simpan history
            CeisaStatusHistory::create([
                'pib_document_id' => $pib->id,
                'status' => $parsed->status ?? 'UNKNOWN',
                'urgency' => $parsed->urgency,
                'raw_payload' => $this->payload,
                'received_at' => now(),
            ]);

            // Jika ada NOTUL → proses (Fase 3a)
            if ($parsed->hasNotul) {
                app(\App\Services\NotulProcessor::class)->process($this->payload, $pib);
            }

            // Dispatch notifikasi sesuai urgency
            $dispatcher->dispatch($pib, $parsed->status ?? 'UPDATE');
        } catch (\Throwable $e) {
            Log::error('ProcessWebhookJob failed', [
                'payload' => $this->payload,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cari PIB berdasarkan nomor aju, atau buat baru jika belum ada.
     */
    protected function resolvePibDocument($parsed): PibDocument
    {
        if (!empty($parsed->ajuNumber)) {
            return PibDocument::firstOrCreate(
                ['aju_number' => $parsed->ajuNumber],
                ['status' => 'draft'],
            );
        }

        // Fallback: buat placeholder (jarang terjadi)
        return PibDocument::create(['status' => 'draft']);
    }
}