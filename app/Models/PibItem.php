<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PibItem extends Model
{
    protected $fillable = [
        'pib_document_id',
        'seri',
        'hs_code',
        'uraian_barang',
        'negara_asal',
        'jumlah_satuan',
        'satuan',
        'nilai_cif',
        'bea_masuk',
        'ppn',
        'pph',
    ];

    protected $casts = [
        'seri' => 'integer',
        'jumlah_satuan' => 'decimal:4',
        'nilai_cif' => 'decimal:2',
        'bea_masuk' => 'decimal:2',
        'ppn' => 'decimal:2',
        'pph' => 'decimal:2',
    ];

    public function pibDocument(): BelongsTo
    {
        return $this->belongsTo(PibDocument::class);
    }
}