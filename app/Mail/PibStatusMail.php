<?php

namespace App\Mail;

use App\Models\PibDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Fase 5.3 — Email untuk status PIB non-NOTUL (ringkas).
 */
class PibStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PibDocument $doc, public array $data)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[PIB] {$this->doc->aju_number} - Status: {$this->doc->status}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.pib-status', with: [
            'doc' => $this->doc,
            'data' => $this->data,
        ]);
    }
}