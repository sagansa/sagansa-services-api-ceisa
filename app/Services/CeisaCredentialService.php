<?php

namespace App\Services;

use App\Models\CeisaCredential;
use Illuminate\Support\Facades\Crypt;

/**
 * Fitur 1 — Manajemen Kredensial & Autentikasi.
 *
 * Membaca kredensial CEISA dari DB (encrypted) dengan fallback ke config/env,
 * serta menyediakan URL gateway sesuai mode (sandbox/production).
 *
 * Catatan auth (VERIFIED dari OpenAPI Portal BC):
 *   - Gateway memakai DUA lapis: API Key (header `beacukai-api-key`) + OAuth2
 *     Bearer (header `Authorization`).
 *   - Field OAuth2 (client_id, client_secret, token_url, access_token,
 *     token_expires_at) optional — jika kosong, fallback ke config('ceisa.oauth').
 */
class CeisaCredentialService
{
    /**
     * Ambil kredensial aktif (DB first, fallback ke config).
     *
     * @return array{
     *     application_id: string,
     *     api_key: string,
     *     client_id: string,
     *     client_secret: string,
     *     token_url: string,
     *     access_token: string,
     *     token_expires_at: ?string,
     *     gateway_mode: string
     * }
     */
    public function getCredentials(): array
    {
        $active = CeisaCredential::active();

        if ($active) {
            return [
                'application_id'   => $active->application_id ?? '', // auto-decrypted via cast
                'api_key'          => $active->api_key ?? '',        // auto-decrypted via cast
                'client_id'        => $active->client_id ?? '',     // auto-decrypted via cast
                'client_secret'    => $active->client_secret ?? '', // auto-decrypted via cast
                'token_url'        => $active->token_url ?? '',
                'access_token'     => $active->access_token ?? '',  // auto-decrypted via cast
                'token_expires_at' => $active->token_expires_at?->toIso8601String(),
                'gateway_mode'     => $active->gateway_mode,
            ];
        }

        // Fallback ke config (env). Untuk OAuth2, gunakan config per-mode.
        $mode = (string) config('ceisa.gateway_mode', 'sandbox');

        return [
            'application_id'   => (string) config('ceisa.application_id'),
            'api_key'          => (string) config('ceisa.api_key'),
            'client_id'        => (string) config("ceisa.oauth.{$mode}.client_id") ?: (string) config('ceisa.client_id'),
            'client_secret'    => (string) config("ceisa.oauth.{$mode}.client_secret") ?: (string) config('ceisa.client_secret'),
            'token_url'        => (string) config("ceisa.oauth.{$mode}.token_url"),
            'access_token'     => '',
            'token_expires_at' => null,
            'gateway_mode'     => $mode,
        ];
    }

    /**
     * Simpan / update kredensial aktif.
     *
     * Field OAuth2 optional — bila tidak dikirim, tidak diubah (jika update)
     * atau null (jika baru). Token cache tidak di-set dari sini; biarkan
     * CeisaOAuthService yang mengisinya saat pertama kali request.
     */
    public function updateCredentials(
        string $applicationId,
        string $apiKey,
        string $gatewayMode = 'sandbox',
        ?string $clientId = null,
        ?string $clientSecret = null,
        ?string $tokenUrl = null,
    ): CeisaCredential {
        // Non-aktifkan kredensial lama
        CeisaCredential::query()->update(['is_active' => false]);

        return CeisaCredential::create([
            'application_id' => $applicationId, // auto-encrypted via cast
            'api_key'        => $apiKey,        // auto-encrypted via cast
            'client_id'      => $clientId,      // auto-encrypted via cast (null-tolerant)
            'client_secret'  => $clientSecret,  // auto-encrypted via cast (null-tolerant)
            'token_url'      => $tokenUrl,
            'gateway_mode'   => in_array($gatewayMode, ['sandbox', 'production']) ? $gatewayMode : 'sandbox',
            'is_active'      => true,
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
     * Base URL lengkap untuk sebuah service (gateway + openapi_path v2).
     *
     * Sejak CEISA OpenAPI v2, seluruh endpoint (manifes, pib, status, cnpibk,
     * gate, file, referensi, dll) berada di bawah SATU base path unified:
     *
     *   {gateway}/v2/openapi
     *
     * Contoh: buildServiceBaseUrl('manifes')
     *   → https://apis-gw.beacukai.go.id/v2/openapi
     *
     * Parameter $service dipertahankan untuk backward-compat dengan
     * CeisaClient->client('manifes'|'pib') versi lama, tetapi sejak v2
     * parameter ini tidak lagi mempengaruhi hasil (semua service sama base-nya).
     */
    public function buildServiceBaseUrl(string $service = ''): string
    {
        $gateway = rtrim($this->getGatewayUrl(), '/');

        // v2 unified path (default: /v2/openapi)
        $openapiPath = (string) config('ceisa.openapi_path', '/v2/openapi');

        // Legacy per-service path (sejak v2 default empty string). Jika
        // didefinisikan via env, gunakan itu (memungkinkan override per-service
        // untuk kebutuhan testing/debugging).
        $servicePath = '';
        if ($service !== '') {
            $servicePath = (string) (config("ceisa.service_paths.{$service}") ?? '');
        }

        // Prioritas: service_path eksplisit (non-empty) > openapi_path v2.
        return rtrim($servicePath !== '' ? ($gateway . $servicePath) : ($gateway . $openapiPath), '/');
    }

    /**
     * Mode aktif (sandbox/production).
     */
    public function getGatewayMode(): string
    {
        return $this->getCredentials()['gateway_mode'] ?? 'sandbox';
    }

    /**
     * Apakah kredensial dasar (API Key) sudah dikonfigurasi?
     */
    public function isConfigured(): bool
    {
        $creds = $this->getCredentials();

        return !empty($creds['application_id']) && !empty($creds['api_key']);
    }

    /**
     * Apakah OAuth2 sudah dikonfigurasi?
     */
    public function isOAuthConfigured(): bool
    {
        $creds = $this->getCredentials();

        return !empty($creds['client_id']) && !empty($creds['client_secret']);
    }

    /**
     * Active credential model (untuk manipulasi token cache oleh OAuth service).
     */
    public function getActiveCredential(): ?CeisaCredential
    {
        return CeisaCredential::active();
    }
}