<?php

namespace App\Services;

use App\Models\CeisaApiLog;
use App\Models\CeisaCredential;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;

/**
 * OAuth2 Client Credentials Grant untuk gateway CEISA 4.0.
 *
 * Menghasilkan Bearer token untuk header `Authorization`. Token di-cache di
 * tabel `ceisa_credentials.access_token` (encrypted) dengan `token_expires_at`.
 * Saat dipanggil lagi dan token masih valid (dengan safety margin), cache
 * dipakai. Jika mendekati expiry / invalid, token baru di-request ke token_url.
 *
 * Sumber: kebijakan "oauth2" (type: OAUTH2) dari metadata OpenAPI Portal BC.
 */
class CeisaOAuthService
{
    public function __construct(protected CeisaCredentialService $credentials)
    {
    }

    /**
     * Ambil access_token valid (cache atau refresh).
     *
     * @param bool $forceRefresh Abaikan cache dan request token baru.
     * @return string|null Bearer token, atau null jika OAuth belum dikonfigurasi.
     */
    public function getAccessToken(bool $forceRefresh = false): ?string
    {
        $creds = $this->credentials->getCredentials();

        // OAuth belum dikonfigurasi → caller bertanggung jawab skip Authorization header.
        if (empty($creds['client_id']) || empty($creds['client_secret'])) {
            return null;
        }

        $credential = $this->credentials->getActiveCredential();

        // Gunakan cache jika masih valid (dengan safety margin).
        if (!$forceRefresh && $credential && $this->isTokenFresh($credential)) {
            return $creds['access_token'];
        }

        // Request token baru.
        return $this->requestNewToken($creds, $credential);
    }

    /**
     * Paksa refresh token pada percobaan berikutnya (mis. setelah 401 dari gateway).
     */
    public function invalidateCache(): void
    {
        $credential = $this->credentials->getActiveCredential();
        if ($credential) {
            $credential->forceFill([
                'access_token'     => null,
                'token_expires_at' => null,
            ])->save();
        }
    }

    /**
     * Cek apakah token cache masih cukup segar (lebih dari margin sebelum expiry).
     */
    protected function isTokenFresh(CeisaCredential $credential): bool
    {
        if (empty($credential->access_token) || $credential->token_expires_at === null) {
            return false;
        }

        $margin = (int) config('ceisa.oauth.token_refresh_margin', 60);

        return $credential->token_expires_at->subSeconds($margin)->isFuture();
    }

    /**
     * Request token baru ke token_url dengan Client Credentials Grant.
     */
    protected function requestNewToken(array $creds, ?CeisaCredential $credential): ?string
    {
        $tokenUrl = $creds['token_url'];
        $clientId = $creds['client_id'];
        $clientSecret = $creds['client_secret'];
        $grantType = (string) config('ceisa.oauth.grant_type', 'client_credentials');
        $scope = (string) config('ceisa.oauth.scope', '');

        if (empty($tokenUrl)) {
            Log::warning('CeisaOAuthService: token_url kosong — tidak bisa minta token.');
            return null;
        }

        $start = microtime(true);

        try {
            $client = new Client([
                'timeout' => (float) config('ceisa.http_timeout', 30),
                'connect_timeout' => (float) config('ceisa.connect_timeout', 10),
                // Paksa HTTP/1.1 (konsisten dengan CeisaClient).
                'version' => 1.1,
                'curl' => [CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1],
            ]);

            $formParams = [
                'grant_type' => $grantType,
            ];
            if ($scope !== '') {
                $formParams['scope'] = $scope;
            }

            $response = $client->post($tokenUrl, [
                RequestOptions::HTTP_ERRORS => false,
                RequestOptions::AUTH => [$clientId, $clientSecret],
                RequestOptions::FORM_PARAMS => $formParams,
                RequestOptions::HEADERS => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
            ]);

            $durationMs = (int) ((microtime(true) - $start) * 1000);
            $raw = (string) $response->getBody();
            $body = json_decode($raw, true) ?? [];
            $code = $response->getStatusCode();

            // Log ke ceisa_api_logs untuk traceability.
            CeisaApiLog::logOutbound(
                endpoint: $tokenUrl,
                method: 'POST',
                request: ['grant_type' => $grantType, 'client_id' => $clientId, 'scope' => $scope],
                response: is_array($body) ? $body : ['raw' => $raw],
                code: $code,
                durationMs: $durationMs,
                error: $code >= 400 ? "OAuth token request failed: HTTP {$code}" : null,
            );

            if ($code < 200 || $code >= 300 || empty($body['access_token'])) {
                Log::error('CeisaOAuthService: gagal minta token', [
                    'http_status' => $code,
                    'body' => $body,
                ]);

                return null;
            }

            $accessToken = $body['access_token'];
            $expiresIn = (int) ($body['expires_in'] ?? config('ceisa.oauth.token_cache_ttl', 3600));
            $expiresAt = now()->addSeconds($expiresIn);

            // Update cache di credential aktif (jika ada — fallback config tidak bisa cache).
            if ($credential) {
                $credential->forceFill([
                    'access_token'     => $accessToken, // auto-encrypted via cast
                    'token_expires_at' => $expiresAt,
                ])->save();
            }

            return $accessToken;
        } catch (\Throwable $e) {
            $durationMs = (int) ((microtime(true) - $start) * 1000);

            CeisaApiLog::logOutbound(
                endpoint: $tokenUrl,
                method: 'POST',
                request: ['grant_type' => $grantType, 'client_id' => $clientId],
                response: ['error' => $e->getMessage()],
                code: 0,
                durationMs: $durationMs,
                error: $e->getMessage(),
            );

            Log::error('CeisaOAuthService: exception saat minta token', [
                'token_url' => $tokenUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}