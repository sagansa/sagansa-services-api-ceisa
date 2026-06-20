<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PibSupportingDoc extends Model
{
    protected $fillable = [
        'pib_document_id',
        'type',
        'file_path',
        'original_name',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function pibDocument(): BelongsTo
    {
        return $this->belongsTo(PibDocument::class);
    }
}
