<?php

use App\Http\Controllers\Api\V1\CeisaWebhookController;
use Illuminate\Support\Facades\Route;

/**
 * Webhook CEISA 4.0 (Inbound).
 * Public route: verifikasi via signature (CEISA_WEBHOOK_SECRET) di controller.
 * NFR: harus respon 200 OK < 2 detik → dispatch job async.
 */
Route::post('/ceisa-webhook', [CeisaWebhookController::class, 'handle'])
    ->name('ceisa.webhook');