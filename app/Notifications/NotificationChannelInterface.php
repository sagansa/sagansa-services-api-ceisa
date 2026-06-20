<?php

namespace App\Notifications;

use App\Models\PibDocument;

/**
 * Fase 5 — Interface untuk semua channel notifikasi.
 */
interface NotificationChannelInterface
{
    /**
     * Kirim notifikasi untuk sebuah event PIB.
     *
     * @param  array  $data  Data kontekstual (notul, amounts, dsb.)
     */
    public function send(PibDocument $doc, string $event, array $data): bool;

    /**
     * Nama channel (fcm|telegram|email).
     */
    public function channel(): string;
}