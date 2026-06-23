<?php

use App\Http\Controllers\Api\V1\CnpibkController;
use Illuminate\Support\Facades\Route;

/**
 * CNPIBK (CEISA 4.0 OpenAPI v2 — cnpibk-controller).
 *
 * Enterprise tier only. Barang kiriman (postal items, e-commerce).
 * 21 endpoints dikelompokkan per kategori.
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: cnpibk-controller.
 */
Route::prefix('cnpibk')->group(function () {
    // Kirim Data
    Route::post('/kirim/impor', [CnpibkController::class, 'kirimImpor'])->name('cnpibk.kirim-impor');
    Route::post('/kirim/ekspor', [CnpibkController::class, 'kirimEkspor'])->name('cnpibk.kirim-ekspor');
    Route::post('/kirim/pkbk', [CnpibkController::class, 'kirimPkbk'])->name('cnpibk.kirim-pkbk');

    // BC 1.1 / BC 1.4
    Route::post('/bc11/update', [CnpibkController::class, 'updateBc11'])->name('cnpibk.bc11-update');
    Route::post('/bc14/kirim', [CnpibkController::class, 'kirimBc14'])->name('cnpibk.bc14-kirim');
    Route::post('/bc14/pecah-pos', [CnpibkController::class, 'pecahPosBc14'])->name('cnpibk.bc14-pecah-pos');
    Route::get('/bc14/status/{noAju}', [CnpibkController::class, 'statusBc14'])->name('cnpibk.bc14-status');

    // Billing & Referensi
    Route::get('/billing/tarik', [CnpibkController::class, 'tarikBilling'])->name('cnpibk.billing-tarik');
    Route::get('/billing/tarik/{kodeBilling}', [CnpibkController::class, 'tarikBillingByKode'])->name('cnpibk.billing-by-kode');
    Route::post('/daftar-tertentu/kirim', [CnpibkController::class, 'kirimDaftarTertentu'])->name('cnpibk.daftar-tertentu');
    Route::get('/e-catalogue', [CnpibkController::class, 'eCatalogue'])->name('cnpibk.e-catalogue');
    Route::get('/e-invoice', [CnpibkController::class, 'eInvoice'])->name('cnpibk.e-invoice');

    // Respon
    Route::get('/respon/tarik/{nomorAju}', [CnpibkController::class, 'tarikRespon'])->name('cnpibk.respon-tarik');
    Route::get('/respon/by-aju/{nomorAju}', [CnpibkController::class, 'responByAju'])->name('cnpibk.respon-by-aju');
    Route::get('/respon/by-status/{status}', [CnpibkController::class, 'responByStatus'])->name('cnpibk.respon-by-status');
    Route::get('/respon/ekspor-by-dokumen/{nomorAju}', [CnpibkController::class, 'eksporByDokumen'])->name('cnpibk.ekspor-by-dokumen');
    Route::get('/respon/ekspor-by-tgl-daftar/{tanggal}', [CnpibkController::class, 'eksporByTglDaftar'])->name('cnpibk.ekspor-by-tgl-daftar');
    Route::get('/respon/ekspor-by-tgl-submit/{tanggal}', [CnpibkController::class, 'eksporByTglSubmit'])->name('cnpibk.ekspor-by-tgl-submit');

    // X-Ray
    Route::post('/xray/add', [CnpibkController::class, 'addFotoXray'])->name('cnpibk.xray-add');
    Route::get('/xray/get/{id}', [CnpibkController::class, 'getFotoXray'])->name('cnpibk.xray-get');
    Route::post('/xray/kirim', [CnpibkController::class, 'kirimFotoXray'])->name('cnpibk.xray-kirim');
});
