<?php

use App\Http\Controllers\Api\V1\FileController;
use Illuminate\Support\Facades\Route;

/**
 * File Upload (CEISA 4.0 OpenAPI v2 — file-controller).
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: file-controller.
 *
 *   POST /v1/file/barang           → upload file barang (detil items)
 *   POST /v1/file/dokumen          → upload file dokumen (invoice, BL, PL)
 *   POST /v1/file/dokap-npd        → upload file DOKAP/NPD
 *
 * Accept multipart/form-data.
 */
Route::prefix('file')->group(function () {
    Route::post('/barang', [FileController::class, 'barang'])->name('file.barang');
    Route::post('/dokumen', [FileController::class, 'dokumen'])->name('file.dokumen');
    Route::post('/dokap-npd', [FileController::class, 'dokapNpd'])->name('file.dokap-npd');
});
