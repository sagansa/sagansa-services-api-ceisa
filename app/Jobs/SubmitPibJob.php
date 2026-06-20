<?php

namespace App\Jobs;

use App\Models\PibDocument;
use App\Services\PibSubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fase 3 — Async PIB submission with auto-retry.
 *
 * NFR: retry tiap 5 menit, maksimal 3x → tries=3, backoff=300.
 */
class SubmitPibJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** Backoff detik: 300 = 5 menit (NFR). */
    public int $backoff = 300;

    public function __construct(public int $pibDocumentId)
    {
    }

    public function handle(PibSubmissionService $service): void
    {
        $doc = PibDocument::find($this->pibDocumentId);
        if (!$doc) {
            Log::warning('SubmitPibJob: PIB not found', ['id' => $this->pibDocumentId]);

            return;
        }

        $result = $service->submit($doc);

        if (!$result['success']) {
            // Lempar exception agar queue retry (sesuai tries/backoff)
            throw new \RuntimeException(implode('; ', $result['errors']));
        }
    }
}