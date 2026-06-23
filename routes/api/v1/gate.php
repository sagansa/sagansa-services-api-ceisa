<?php

use App\Http\Controllers\Api\V1\GateController;
use Illuminate\Support\Facades\Route;

/**
 * Gate TPB (CEISA 4.0 OpenAPI v2 — gate-controller).
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: gate-controller.
 *
 *   GET  /v1/gate/dokumen/{nomorAju}   → data dokumen gate-in
 *   POST /v1/gate/kemasan/in           → gate-in kemasan
 *   POST /v1/gate/kemasan/out          → gate-out kemasan
 *   POST /v1/gate/kontainer/in         → gate-in kontainer
 *   POST /v1/gate/rekam/bongkar        → rekam hasil bongkar
 *   POST /v1/gate/rekam/stuffing       → rekam hasil stuffing
 *
 * Dipakai operator TPB untuk rekam pergerakan barang masuk/keluar gudang.
 */
Route::prefix('gate')->group(function () {
    Route::get('/dokumen/{nomorAju}', [GateController::class, 'dokumen'])
        ->where('nomorAju', '[0-9A-Za-z]+')
        ->name('gate.dokumen');

    Route::post('/kemasan/in', [GateController::class, 'kemasanIn'])->name('gate.kemasan-in');
    Route::post('/kemasan/out', [GateController::class, 'kemasanOut'])->name('gate.kemasan-out');
    Route::post('/kontainer/in', [GateController::class, 'kontainerIn'])->name('gate.kontainer-in');
    Route::post('/rekam/bongkar', [GateController::class, 'rekamBongkar'])->name('gate.rekam-bongkar');
    Route::post('/rekam/stuffing', [GateController::class, 'rekamStuffing'])->name('gate.rekam-stuffing');
});
