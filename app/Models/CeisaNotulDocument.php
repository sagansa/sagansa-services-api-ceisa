<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fitur 3a — NOTUL/SPTNP & Underprice.
 */
class CeisaNotulDocument extends Model
{
    protected $fillable = [
        'pib_document_id',
        'nomor_surat',
        'tanggal_surat',
        'hs_code',
        'uraian_barang',
        'nilai_deklarasi',
        'nilai_penetapan_bc',
        'selisih_bea_masuk',
        'denda',
        'ppn_pph',
        'total_kewajiban',
        'is_underprice',
        'jenis_surat',
        'due_date_ssp',
        'rekening_ssp',
        'file_path',
        'raw_payload',
    ];

    protected $casts = [
        'nilai_deklarasi' => 'decimal:2',
        'nilai_penetapan_bc' => 'decimal:2',
        'selisih_bea_masuk' => 'decimal:2',
        'denda' => 'decimal:2',
        'ppn_pph' => 'decimal:2',
        'total_kewajiban' => 'decimal:2',
        'is_underprice' => 'boolean',
        'tanggal_surat' => 'date',
        'due_date_ssp' => 'date',
        'raw_payload' => 'array',
    ];

    public function pibDocument(): BelongsTo
    {
        return $this->belongsTo(PibDocument::class);
    }
}