<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CeisaUserAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CEISA 4.0 H2H User Authentication Controller.
 *
 * Membungkus endpoint openapi-auth (Portal BC):
 *   - POST /v1/auth/user/login        → login user H2H BC
 *   - POST /v1/auth/user/update-token → refresh token user H2H BC
 *
 * Sumber: doc/json/Export_openapi-auth-v2.json (API openapi-auth v1).
 *
 * Catatan keamanan:
 *  - Password TIDAK di-persist oleh backend SAGANSA; hanya diteruskan ke BC.
 *  - Token user yang dikembalikan BC dikembalikan ke client untuk disimpan
 *    di SecureStore (mobile). Backend bisa opsi-on untuk caching (lihat
 *    method response).
 *  - Field sensitif di-mask sebelum logging (lihat CeisaUserAuthService).
 */
class CeisaUserAuthController extends Controller
{
    public function __construct(protected CeisaUserAuthService $service)
    {
    }

    /**
     * Login user H2H ke gateway CEISA.
     *
     * Body: { username, password } (opsional: token untuk SSO/OTP-style).
     * Response BC (tidak ter-schema secara ketat) umumnya memuat token user.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required_without:token', 'string', 'max:255'],
            'password' => ['required_without:token', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:1024'],
        ]);

        $result = $this->service->login($data);

        return $this->proxyResponse($result);
    }

    /**
     * Update / refresh token user H2H.
     *
     * Body: { token } atau { refresh_token }. Bila kosong, backend mencoba
     * memakai token yang sudah di-cache (bila ada).
     */
    public function updateToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:2048'],
            'refresh_token' => ['nullable', 'string', 'max:2048'],
        ]);

        $result = $this->service->updateToken($data);

        return $this->proxyResponse($result);
    }

    /**
     * Format response proxy konsisten untuk login & update-token.
     *
     * Saat gateway BC mengembalikan error (>=400) atau body kosong, sertakan
     * field `debug` berisi URL yang dihit + raw body BC agar mudah
     * didiagnosis. Field ini hanya muncul saat error (sukses tetap bersih).
     *
     * Normalisasi token (sejak pengetesan langsung ke gateway BC, 2026-06-22):
     * Response BC sebenarnya MEMBUNGKUS token di field `item`:
     *   { status: "success", message: "...", item: { access_token, refresh_token, ... } }
     * Untuk konsistensi & backward-compat dengan client lama yang mengharapkan
     * token di root, kita "flatten" item ke root saat sukses. Field `item`
     * tetap dipertahankan apa adanya (tidak dihapus) demi traceability.
     */
    protected function proxyResponse(array $result): JsonResponse
    {
        $status = $result['status'] ?? 0;
        $body = $result['body'] ?? [];
        $raw = $result['raw'] ?? '';
        $isOk = $status >= 200 && $status < 300;

        // Normalisasi: flatten item.* ke root agar token mudah di-parse client.
        if ($isOk && is_array($body) && isset($body['item']) && is_array($body['item'])) {
            foreach ($body['item'] as $key => $value) {
                if (!array_key_exists($key, $body)) {
                    $body[$key] = $value;
                }
            }
        }

        $payload = [
            'status' => $status,
            'data' => $body,
        ];

        // Sertakan diagnostic saat error ATAU body kosong (mis. BC return
        // empty 404). Membantu admin mengetahui URL mana yang dihit BC.
        if (!$isOk || ($raw !== '' && $raw !== '[]' && $raw !== '{}')) {
            $payload['debug'] = [
                'gateway_url' => $result['endpoint'] ?? null,
                'raw' => $raw,
            ];
        }

        return response()->json($payload, $isOk ? 200 : ($status ?: 502));
    }
}