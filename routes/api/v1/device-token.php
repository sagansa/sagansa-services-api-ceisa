<?php

use App\Http\Controllers\Api\V1\DeviceTokenController;
use Illuminate\Support\Facades\Route;

/**
 * Fase 5.1 — FCM Device Token registration.
 */
Route::post('/device-token', [DeviceTokenController::class, 'store'])->name('device-token.store');
Route::delete('/device-token', [DeviceTokenController::class, 'destroy'])->name('device-token.destroy');