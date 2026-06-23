<?php

namespace App\Mail;

use App\Models\PibDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Fase 5.3 — Email untuk kasus NOTUL/SPTNP (koreksi nilai pabean).
 * Berisi detail denda, selisih bea masuk, rekening SSP, jatuh tempo.
 */
class NotulPibMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PibDocument $doc, public array $data)
    {
    }

    public function envelope(): Envelope
    {
        $subject = $this->doc->is_underprice
            ? "[KOREKSI] PIB {$this->doc->aju_number} - NOTUL/SPTNP"
            : "[NOTUL] PIB {$this->doc->aju_number}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.notul-pib', with: [
            'doc' => $this->doc,
            'data' => $this->data,
            'notul' => $this->doc->latestNotul,
        ]);
    }
}