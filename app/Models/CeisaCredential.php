<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fitur 1 — Kredensial CEISA (encrypted at rest via casts).
 *
 * Menyimpan:
 *  - application_id: Application ID dari portal BC (header "Application-Id" lama;
 *    sebagian API BC modern sudah tidak pakai ini — cek OpenAPI per API).
 *  - api_key        : API Key untuk header `beacukai-api-key` (APIKEY policy).
 *  - client_*       : OAuth2 client credentials untuk Client Credentials Grant
 *                     (menghasilkan Bearer token untuk header Authorization).
 *  - access_token   : cache token terkini (encrypted). Di-refresh oleh
 *                     CeisaOAuthService ketika mendekati token_expires_at.
 */
class CeisaCredential extends Model
{
    protected $fillable = [
        'application_id',
        'api_key',
        // OAuth2
        'client_id',
        'client_secret',
        'token_url',
        'access_token',
        'token_expires_at',
        // Gateway
        'gateway_mode',
        'is_active',
    ];

    protected $casts = [
        'application_id'   => 'encrypted',
        'api_key'          => 'encrypted',
        'client_id'        => 'encrypted',
        'client_secret'    => 'encrypted',
        'access_token'     => 'encrypted',
        'token_expires_at' => 'datetime',
        'gateway_mode'     => 'string',
        'is_active'        => 'boolean',
    ];

    protected $hidden = [
        'api_key',
        'client_secret',
        'access_token',
    ];

    /**
     * Active credential singleton (first active row).
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)->latest('id')->first();
    }

    /**
     * Apakah OAuth2 sudah dikonfigurasi untuk credential ini?
     */
    public function hasOAuthConfigured(): bool
    {
        return !empty($this->client_id)
            && !empty($this->client_secret);
    }

    /**
     * Apakah access_token masih valid (ada & belum mendekati expiry)?
     */
    public function hasValidToken(): bool
    {
        return !empty($this->access_token)
            && $this->token_expires_at !== null
            && $this->token_expires_at->isFuture();
    }
}