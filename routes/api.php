<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CEISA API Routes (v1)
|--------------------------------------------------------------------------
| Endpoint dipanggil oleh: ERP (internal), mobiles/ceisa, dan webhook CEISA.
| Catatan: endpoint webhook (POST /v1/ceisa-webhook) DILINDUNGI tanpa CSRF
| (menggunakan routes/api.php dengan signature verification, bukan auth user).
*/

Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'api-ceisa']));

// Health alias (tanpa prefix version)
Route::get('/v1/health', fn () => response()->json(['status' => 'ok', 'service' => 'api-ceisa']));

Route::prefix('v1')->group(function () {
    // Webhook dari CEISA 4.0 (public, verified via signature)
    require __DIR__.'/api/v1/webhook.php';

    // Fase 2 — Manajemen Kredensial
    require __DIR__.'/api/v1/credentials.php';

    // Manifes (openapi-manifes, CEISA 4.0) — VERIFIED endpoints.
    // Fokus: GET status by nomorAju + draft/kirim/rekon/inward/outward.
    require __DIR__.'/api/v1/manifes.php';

    // Fase 3 — Outbound PIB submit
    require __DIR__.'/api/v1/pib.php';

    // CEISA 4.0 OpenAPI v2 — unified status endpoint (semua dokumen pabean)
    require __DIR__.'/api/v1/status.php';

    // Fase 5 — Device token (FCM)
    require __DIR__.'/api/v1/device-token.php';

    // Fase 6 — List/detail PIB, NOTUL, logs, settings
    require __DIR__.'/api/v1/dashboard.php';
});
