<?php

namespace App\Notifications\Channels;

use App\Models\NotificationSetting;
use App\Models\PibDocument;
use App\Notifications\NotificationChannelInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Fase 5.2 — Telegram Bot channel.
 *
 * Mengirim pesan Markdown ke grup Chat ID.
 * NFR: batas 4096 karakter → auto-truncate.
 */
class TelegramChannel implements NotificationChannelInterface
{
    public function channel(): string
    {
        return 'telegram';
    }

    public function send(PibDocument $doc, string $event, array $data): bool
    {
        $token = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
        if (empty($token)) {
            Log::info('TelegramChannel: token not configured, skipping');

            return false;
        }

        $chatId = $this->resolveChatId();
        if (empty($chatId)) {
            Log::warning('TelegramChannel: chat_id not configured');

            return false;
        }

        $text = $this->buildMessage($doc, $event, $data);

        try {
            $response = Http::withToken('')
                ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'Markdown',
                ]);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::error('TelegramChannel send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    protected function resolveChatId(): ?string
    {
        $setting = NotificationSetting::forChannel('telegram');
        if ($setting && !empty($setting->target_recipient['chat_id'])) {
            return (string) $setting->target_recipient['chat_id'];
        }

        return env('TELEGRAM_CHAT_ID') ?: null;
    }

    /**
     * Build Markdown message (max 4096 chars — NFR).
     */
    protected function buildMessage(PibDocument $doc, string $event, array $data): string
    {
        $label = $doc->is_underprice ? '⚠️ *UNDERPRICE*' : "*{$event}*";
        $lines = [
            "🚨 *Peringatan PIB (BC 2.0)*",
            "",
            "*Status:* {$label}",
            "*No AJU:* `{$doc->aju_number}`",
            $doc->registration_number ? "*No Pendaftaran:* `{$doc->registration_number}`" : null,
            $doc->importir_name ? "*Importir:* {$doc->importir_name}" : null,
        ];

        if (!empty($data['notul'])) {
            $n = $data['notul'];
            $lines[] = "";
            $lines[] = "*📋 NOTUL/SPTNP*";
            $lines[] = $n['nomor_surat'] ? "Surat: {$n['nomor_surat']}" : null;
            $lines[] = "Deklarasi: Rp " . number_format((float) $n['nilai_deklarasi'], 0, ',', '.');
            $lines[] = "Penetapan BC: Rp " . number_format((float) $n['nilai_penetapan_bc'], 0, ',', '.');
            $lines[] = "Selisih Bea Masuk: Rp " . number_format((float) $n['selisih_bea_masuk'], 0, ',', '.');
            $lines[] = "Denda: Rp " . number_format((float) $n['denda'], 0, ',', '.');
            $lines[] = "*Total Kewajiban: Rp " . number_format((float) $n['total_kewajiban'], 0, ',', '.') . "*";
            $lines[] = $n['due_date_ssp'] ? "Jatuh tempo SSP: {$n['due_date_ssp']}" : null;
            $lines[] = $n['rekening_ssp'] ? "Rekening SSP: `{$n['rekening_ssp']}`" : null;
        }

        $message = implode("\n", array_filter($lines));

        // Truncate to 4096 chars (NFR)
        return mb_substr($message, 0, 4090);
    }
}