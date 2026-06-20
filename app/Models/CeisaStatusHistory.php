<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CeisaStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'pib_document_id',
        'status',
        'urgency',
        'raw_payload',
        'received_at',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'received_at' => 'datetime',
    ];

    public function pibDocument(): BelongsTo
    {
        return $this->belongsTo(PibDocument::class);
    }
}