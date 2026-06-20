<?php

namespace App\Services;

use App\Models\CeisaCredential;
use Illuminate\Support\Facades\Crypt;

/**
 * Fitur 1 — Manajemen Kredensial & Autentikasi.
 *
 * Membaca kredensial CEISA dari DB (encrypted) dengan fallback ke config/env,
 * serta menyediakan URL gateway sesuai mode (sandbox/production).
 */
class CeisaCredentialService
{
    /**
     * Ambil kredensial aktif (DB first, fallback ke config).
     *
     * @return array{application_id: string, api_key: string, gateway_mode: string}
     */
    public function getCredentials(): array
    {
        $active = CeisaCredential::active();

        if ($active) {
            return [
                'application_id' => $active->application_id, // auto-decrypted via cast
                'api_key' => $active->api_key,               // auto-decrypted via cast
                'gateway_mode' => $active->gateway_mode,
            ];
        }

        // Fallback ke config (env)
        return [
            'application_id' => (string) config('ceisa.application_id'),
            'api_key' => (string) config('ceisa.api_key'),
            'gateway_mode' => (string) config('ceisa.gateway_mode', 'sandbox'),
        ];
    }

    /**
     * Simpan / update kredensial aktif.
     */
    public function updateCredentials(string $applicationId, string $apiKey, string $gatewayMode = 'sandbox'): CeisaCredential
    {
        // Non-aktifkan kredensial lama
        CeisaCredential::query()->update(['is_active' => false]);

        return CeisaCredential::create([
            'application_id' => $applicationId, // auto-encrypted via cast
            'api_key' => $apiKey,               // auto-encrypted via cast
            'gateway_mode' => in_array($gatewayMode, ['sandbox', 'production']) ? $gatewayMode : 'sandbox',
            'is_active' => true,
        ]);
    }

    /**
     * URL gateway aktif sesuai mode.
     */
    public function getGatewayUrl(): string
    {
        $mode = $this->getCredentials()['gateway_mode'] ?? 'sandbox';

        return (string) (config("ceisa.gateways.{$mode}") ?: config('ceisa.gateways.sandbox'));
    }

    /**
     * Mode aktif (sandbox/production).
     */
    public function getGatewayMode(): string
    {
        return $this->getCredentials()['gateway_mode'] ?? 'sandbox';
    }

    /**
     * Apakah kredensial sudah dikonfigurasi?
     */
    public function isConfigured(): bool
    {
        $creds = $this->getCredentials();

        return !empty($creds['application_id']) && !empty($creds['api_key']);
    }
}