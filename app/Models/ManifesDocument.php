<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dokumen Manifes NVOCC (openapi-manifes, CEISA 4.0).
 *
 * Sumber field: OpenAPI Portal — API "openapi-manifes" (VERIFIED).
 */
class ManifesDocument extends Model
{
    protected $fillable = [
        'nomor_aju',
        'kode_kantor',
        'jenis_manifes',
        'nomor_voyage',
        'nama_sarana_pengangkut',
        'imo_number',
        'mode_pengangkut',
        'kode_negara',
        'status',
        'id_nvocc_header',
        'nomor_bc11',
        'tanggal_bc11',
        'nomor_daftar',
        'drafted_at',
        'submitted_at',
        'rekon_at',
        'last_status_check_at',
        'last_status_response',
    ];

    protected $casts = [
        'tanggal_bc11'           => 'date',
        'drafted_at'             => 'datetime',
        'submitted_at'           => 'datetime',
        'rekon_at'               => 'datetime',
        'last_status_check_at'   => 'datetime',
        'last_status_response'   => 'array',
    ];

    /**
     * Cari by nomor_aju, atau buat baru.
     */
    public static function findByNomorAju(string $nomorAju): ?self
    {
        return static::where('nomor_aju', $nomorAju)->first();
    }

    /**
     * First-or-create by nomor_aju.
     */
    public static function firstOrCreateByNomorAju(string $nomorAju, array $attrs = []): self
    {
        return static::firstOrCreate(
            ['nomor_aju' => $nomorAju],
            $attrs,
        );
    }
}