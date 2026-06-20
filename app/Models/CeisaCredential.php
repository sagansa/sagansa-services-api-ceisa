<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fitur 1 — Kredensial CEISA (encrypted at rest via casts).
 */
class CeisaCredential extends Model
{
    protected $fillable = [
        'application_id',
        'api_key',
        'gateway_mode',
        'is_active',
    ];

    protected $casts = [
        'application_id' => 'encrypted',
        'api_key' => 'encrypted',
        'gateway_mode' => 'string',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
    ];

    /**
     * Active credential singleton (first active row).
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)->latest('id')->first();
    }
}