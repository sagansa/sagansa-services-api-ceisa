<?php

namespace App\Services;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationSetting;
use App\Models\PibDocument;
use Illuminate\Support\Facades\Log;

/**
 * Fase 5 — Multi-channel notification dispatcher.
 *
 * - Cek notification_settings (channel aktif?)
 * - Dispatch SendNotificationJob per channel
 * - Fail-safe: jika Telegram/FCM gagal, Email tetap dikirim
 */
class NotificationDispatcher
{
    /**
     * Dispatch notifikasi untuk sebuah event PIB ke semua channel yang aktif.
     */
    public function dispatch(PibDocument $doc, string $event): void
    {
        $urgency = $this->resolveUrgency($doc, $event);
        $data = $this->buildData($doc, $event, $urgency);

        $channels = ['fcm', 'telegram', 'email'];

        foreach ($channels as $channelName) {
            $setting = NotificationSetting::forChannel($channelName);

            // Skip jika channel dinonaktifkan
            if (!$setting || !$setting->is_enabled) {
                continue;
            }

            // Normal events opsional; urgent events wajib
            if ($urgency === 'normal' && !$setting->notify_normal) {
                continue;
            }

            SendNotificationJob::dispatch($doc->id, $channelName, $event, $urgency, $data);
        }
    }

    /**
     * Klasifikasi urgency berdasar status PIB / event.
     */
    protected function resolveUrgency(PibDocument $doc, string $event): string
    {
        $urgent = (array) config('ceisa.urgency.urgent', []);
        $upper = strtoupper((string) $event);

        if ($doc->is_underprice) {
            return 'urgent';
        }
        foreach ($urgent as $u) {
            if (str_contains($upper, strtoupper($u))) {
                return 'urgent';
            }
        }

        return 'normal';
    }

    /**
     * Data kontekstual untuk payload notifikasi.
     */
    protected function buildData(PibDocument $doc, string $event, string $urgency): array
    {
        $data = [
            'aju_number' => $doc->aju_number,
            'registration_number' => $doc->registration_number,
            'status' => $doc->status,
            'event' => $event,
            'urgency' => $urgency,
            'importir_name' => $doc->importir_name,
            'is_underprice' => $doc->is_underprice,
        ];

        // Sertakan data NOTUL bila ada
        if ($doc->latestNotul) {
            $notul = $doc->latestNotul;
            $data['notul'] = [
                'nomor_surat' => $notul->nomor_surat,
                'nilai_deklarasi' => (string) $notul->nilai_deklarasi,
                'nilai_penetapan_bc' => (string) $notul->nilai_penetapan_bc,
                'selisih_bea_masuk' => (string) $notul->selisih_bea_masuk,
                'denda' => (string) $notul->denda,
                'total_kewajiban' => (string) $notul->total_kewajiban,
                'due_date_ssp' => $notul->due_date_ssp?->format('Y-m-d'),
                'rekening_ssp' => $notul->rekening_ssp,
            ];
        }

        return $data;
    }
}