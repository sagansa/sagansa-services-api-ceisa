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

        return response()->json([
            'status' => $result['status'],
            'data' => $result['body'],
        ], $result['status'] >= 200 && $result['status'] < 300 ? 200 : ($result['status'] ?: 502));
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

        return response()->json([
            'status' => $result['status'],
            'data' => $result['body'],
        ], $result['status'] >= 200 && $result['status'] < 300 ? 200 : ($result['status'] ?: 502));
    }
}