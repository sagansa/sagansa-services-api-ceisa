<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'pib_document_id',
        'channel',
        'event',
        'recipient',
        'subject',
        'payload',
        'status',
        'error_message',
        'attempts',
        'sent_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'sent_at' => 'datetime',
    ];

    public function pibDocument(): BelongsTo
    {
        return $this->belongsTo(PibDocument::class);
    }
}