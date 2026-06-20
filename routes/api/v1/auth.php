<?php

use App\Http\Controllers\Api\V1\CeisaUserAuthController;
use Illuminate\Support\Facades\Route;

/**
 * CEISA 4.0 H2H User Authentication (openapi-auth v1).
 *
 * Sumber: doc/json/Export_openapi-auth-v2.json
 *   Base URL gateway BC: {gateway}/v1/openapi-auth
 *   - POST /user/login
 *   - POST /user/update-token
 *
 * Backend SAGANSA membedakan dua lapis auth:
 *  1) OAuth2 (Client Credentials) — Bearer gateway (CeisaOAuthService)
 *  2) User H2H (username/password) — identitas user BC (CeisaUserAuthService)
 *
 * Route di bawah menangani lapisan #2. Bearer gateway tetap diinject otomatis
 * oleh CeisaUserAuthService saat memanggil gateway BC.
 */
Route::prefix('auth')->group(function () {
    Route::post('/user/login', [CeisaUserAuthController::class, 'login'])->name('ceisa-auth.user-login');
    Route::post('/user/update-token', [CeisaUserAuthController::class, 'updateToken'])->name('ceisa-auth.user-update-token');
});