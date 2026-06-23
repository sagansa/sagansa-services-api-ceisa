<?php

use App\Http\Controllers\Api\V1\CukaiController;
use Illuminate\Support\Facades\Route;

/**
 * Cukai (CEISA 4.0 Host to Host Cukai — openapi-cukai v1.0).
 *
 * Enterprise tier only. Barang Kena Cukai (BKC):
 * rokok (hasil tembakau), MMEA (alkohol), mirasantisa.
 *
 * 13 endpoint dikelompokkan 4 kategori: GPS, Mesin, Produksi, Referensi.
 * Base path gateway: {gateway}/v1/openapi-cukai (terpisah dari /v2/openapi).
 *
 * Sumber: portal BC → openapi-cukai v1.0 (openapicukai_openapi.json).
 */
Route::prefix('cukai')->group(function () {
    // GPS — tracking lokasi mesin produksi
    Route::get('/gps', [CukaiController::class, 'listGps'])->name('cukai.gps-list');
    Route::get('/gps/{id}', [CukaiController::class, 'getGps'])->name('cukai.gps-by-id');
    Route::post('/gps', [CukaiController::class, 'createGps'])->name('cukai.gps-create');

    // Mesin — CRUD mesin produksi BKC
    Route::get('/mesin', [CukaiController::class, 'listMesin'])->name('cukai.mesin-list');
    Route::post('/mesin', [CukaiController::class, 'createMesin'])->name('cukai.mesin-create');
    Route::put('/mesin/{id}', [CukaiController::class, 'updateMesin'])->name('cukai.mesin-update');
    Route::delete('/mesin/{id}', [CukaiController::class, 'deleteMesin'])->name('cukai.mesin-delete');

    // Produksi — laporan produksi (batang = per keping, kemasan = per pack)
    Route::get('/produksi', [CukaiController::class, 'listProduksi'])->name('cukai.produksi-list');
    Route::post('/produksi/batang', [CukaiController::class, 'createProduksiBatang'])->name('cukai.produksi-batang');
    Route::post('/produksi/batang/batch', [CukaiController::class, 'createProduksiBatangBatch'])->name('cukai.produksi-batang-batch');
    Route::post('/produksi/kemasan', [CukaiController::class, 'createProduksiKemasan'])->name('cukai.produksi-kemasan');
    Route::post('/produksi/kemasan/batch', [CukaiController::class, 'createProduksiKemasanBatch'])->name('cukai.produksi-kemasan-batch');

    // Referensi — master data untuk dropdown form mesin
    Route::get('/referensi/jenis-mesin', [CukaiController::class, 'refJenisMesin'])->name('cukai.ref-jenis-mesin');
    Route::get('/referensi/tipe-mesin', [CukaiController::class, 'refTipeMesin'])->name('cukai.ref-tipe-mesin');
    Route::get('/referensi/kondisi', [CukaiController::class, 'refKondisi'])->name('cukai.ref-kondisi');
    Route::get('/referensi/status-kepemilikan', [CukaiController::class, 'refStatusKepemilikan'])->name('cukai.ref-status-kepemilikan');
});
