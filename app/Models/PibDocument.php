<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * BC 2.0 — Pemberitahuan Impor Barang (PIB).
 */
class PibDocument extends Model
{
    protected $fillable = [
        'aju_number',
        'registration_number',
        'kantor_pabean',
        'jenis_pib',
        'jenis_impor',
        'cara_pembayaran',
        'importir_npwp',
        'importir_nitku',
        'importir_name',
        'importir_alamat',
        'importir_negara',
        'pemilik_nib',
        'pemilik_nama',
        'pemilik_alamat',
        'pemusatan_nib',
        'pemusatan_nama',
        'pemusatan_alamat',
        'pengirim_nama',
        'pengirim_alamat',
        'pengirim_negara',
        'penjual_nama',
        'penjual_alamat',
        'penjual_negara',
        'ppjk_npwp',
        'jenis_transaksi',
        'sarana_angkut',
        'pelabuhan_muat',
        'pelabuhan_bongkar',
        'status',
        'valuation_declaration',
        'valuation_final',
        'is_underprice',
        'due_date_ssp',
        'ceisa_response_id',
        'ceisa_reference',
        'submitted_at',
        'last_webhook_at',
    ];

    protected $casts = [
        'valuation_declaration' => 'decimal:2',
        'valuation_final' => 'decimal:2',
        'is_underprice' => 'boolean',
        'due_date_ssp' => 'date',
        'submitted_at' => 'datetime',
        'last_webhook_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PibItem::class);
    }

    public function supportingDocs(): HasMany
    {
        return $this->hasMany(PibSupportingDoc::class);
    }

    public function notulDocuments(): HasMany
    {
        return $this->hasMany(CeisaNotulDocument::class);
    }

    public function latestNotul(): HasOne
    {
        return $this->hasOne(CeisaNotulDocument::class)->latestOfMany();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(CeisaStatusHistory::class);
    }

    public function notificationLogs(): HasMany
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * Scope: only underprice / NOTUL documents.
     */
    public function scopeUnderprice($query)
    {
        return $query->where('is_underprice', true);
    }
}