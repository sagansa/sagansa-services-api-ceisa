<?php

use App\Http\Controllers\Api\V1\StatusController;
use Illuminate\Support\Facades\Route;

/**
 * Status (CEISA 4.0 OpenAPI v2 — unified status endpoint).
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: status-controller.
 *
 *   GET /v1/status/{nomorAju}     → history status + respon by nomorAju
 *   GET /v1/status?nitku=<NITKU>  → list dokumen belum diambil by NITKU
 *
 * Catatan: v1 Manifes status (/v1/manifes/status/{nomorAju}) tetap dipertahankan
 * sebagai alias untuk backward-compat; endpoint baru ini adalah kanonik untuk
 * semua dokumen pabean (PIB, BC 2.3, BC 3.0, TPB, dll).
 */
Route::prefix('status')->group(function () {
    // by NITKU (query) — wajib sebelum /{nomorAju} agar tidak tertangkap.
    Route::get('/', [StatusController::class, 'show'])->name('status.by-nitku');

    // by nomorAju (path).
    Route::get('/{nomorAju}', [StatusController::class, 'show'])
        ->where('nomorAju', '[0-9A-Za-z]+')
        ->name('status.by-aju');
});