<?php

namespace App\Jobs;

use App\Models\CeisaNotulDocument;
use App\Services\NotificationDispatcher;
use App\Models\PibDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Fase 4 — Scheduled reminder untuk NOTUL yang mendekati due_date_ssp.
 *
 * Cron tiap jam: cek NOTUL H-3 dan H-1 sebelum jatuh tempo → kirim reminder.
 */
class NotulReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(NotificationDispatcher $dispatcher): void
    {
        $today = now()->startOfDay();

        foreach ([3, 1] as $daysBefore) {
            $targetDate = $today->copy()->addDays($daysBefore)->toDateString();

            $notuls = CeisaNotulDocument::whereNotNull('due_date_ssp')
                ->whereDate('due_date_ssp', $targetDate)
                ->where('total_kewajiban', '>', 0)
                ->get();

            foreach ($notuls as $notul) {
                $pib = PibDocument::find($notul->pib_document_id);
                if (!$pib) {
                    continue;
                }

                $dispatcher->dispatch($pib, 'REMINDER_SSP');

                Log::info('NotulReminder sent', [
                    'pib_id' => $pib->id,
                    'notul_id' => $notul->id,
                    'days_before' => $daysBefore,
                    'due_date' => $notul->due_date_ssp,
                ]);
            }
        }
    }
}