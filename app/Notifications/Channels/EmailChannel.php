<?php

namespace App\Notifications\Channels;

use App\Mail\NotulPibMail;
use App\Mail\PibStatusMail;
use App\Models\NotificationSetting;
use App\Models\PibDocument;
use App\Notifications\NotificationChannelInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Fase 5.3 — Email (SMTP) channel.
 *
 * Template:
 * - NOTUL/underprice → emails.notul-pib (detail denda, selisih, rekening SSP)
 * - Status lain → emails.pib-status (ringkas)
 */
class EmailChannel implements NotificationChannelInterface
{
    public function channel(): string
    {
        return 'email';
    }

    public function send(PibDocument $doc, string $event, array $data): bool
    {
        $setting = NotificationSetting::forChannel('email');
        $recipients = $this->resolveRecipients($setting);

        if (empty($recipients)) {
            Log::warning('EmailChannel: no recipients configured');

            return false;
        }

        try {
            $isNotul = $doc->is_underprice || in_array(strtoupper($event), ['NOTUL', 'SPTNP', 'DENDA']);

            foreach ($recipients as $to) {
                if ($isNotul) {
                    Mail::to($to)->send(new NotulPibMail($doc, $data));
                } else {
                    Mail::to($to)->send(new PibStatusMail($doc, $data));
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('EmailChannel send failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Daftar penerima email dari setting atau env default.
     */
    protected function resolveRecipients(?NotificationSetting $setting): array
    {
        if ($setting && !empty($setting->target_recipient['to'])) {
            return (array) $setting->target_recipient['to'];
        }

        $default = config('mail.from.address');

        return $default ? [$default] : [];
    }
}