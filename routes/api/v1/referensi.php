<?php

use App\Http\Controllers\Api\V1\ReferensiController;
use Illuminate\Support\Facades\Route;

/**
 * Referensi & Kurs (CEISA 4.0 OpenAPI v2).
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: referensi-controller.
 *
 *   GET /v1/referensi/pelabuhan-dalam/{kodeKantor}
 *   GET /v1/referensi/pelabuhan-luar/{kata}
 *   GET /v1/referensi/tps-gudang/{kodeKantor}
 *   GET /v1/kurs/{kodeValuta}
 *   GET /v1/referensi/pungutan/{nomorAju}
 *
 * Dipakai operator: lookup master data + kurs NDPBM + hitung pungutan PIB.
 */
Route::prefix('referensi')->group(function () {
    Route::get('/pelabuhan-dalam/{kodeKantor}', [ReferensiController::class, 'pelabuhanDalam'])
        ->where('kodeKantor', '[0-9A-Za-z]+')
        ->name('ref.pelabuhan-dalam');

    Route::get('/pelabuhan-luar/{kata}', [ReferensiController::class, 'pelabuhanLuar'])
        ->where('kata', '[^/]+')
        ->name('ref.pelabuhan-luar');

    Route::get('/tps-gudang/{kodeKantor}', [ReferensiController::class, 'tpsGudang'])
        ->where('kodeKantor', '[0-9A-Za-z]+')
        ->name('ref.tps-gudang');

    Route::get('/pungutan/{nomorAju}', [ReferensiController::class, 'pungutan'])
        ->where('nomorAju', '[0-9A-Za-z]+')
        ->name('ref.pungutan');
});

// Kurs dipisah dari prefix /referensi agar URL-nya /v1/kurs/{kodeValuta}
// (sesuai endpoint BC asli).
Route::prefix('kurs')->group(function () {
    Route::get('/{kodeValuta}', [ReferensiController::class, 'kurs'])
        ->where('kodeValuta', '[A-Z]{3}')
        ->name('ref.kurs');
});
