<?php

use App\Http\Controllers\Api\V1\ResponController;
use Illuminate\Support\Facades\Route;

/**
 * Respon & PDF (CEISA 4.0 OpenAPI v2 — respon-controller).
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: respon-controller.
 * Semua endpoint mengembalikan PDF binary (bukan JSON).
 *
 *   GET /v1/respon/pdf?kodeRespon=&nomorAju=            → PDF Respon
 *   GET /v1/respon/billing?kodeBilling=                 → Billing PDF
 *   GET /v1/respon/formulir?nomorAju=                   → Formulir PDF
 *   GET /v1/respon/formulir/draft?nomorAju=             → Formulir Draft PDF
 *   GET /v1/respon/formulir/final?nomorAju=             → Formulir Final PDF
 *   GET /v1/respon/npe-bc33/{kodeDokumen}/{tanggalDokumen}?kodeGudang= → NPE BC 3.3
 *   GET /v1/respon/download?path=                       → Download PDF Respon
 *
 * Query param ?inline=1 → preview di browser (Content-Disposition: inline).
 * Default → force download (Content-Disposition: attachment).
 */
Route::prefix('respon')->group(function () {
    Route::get('/pdf', [ResponController::class, 'pdf'])->name('respon.pdf');
    Route::get('/billing', [ResponController::class, 'billing'])->name('respon.billing');
    Route::get('/formulir', [ResponController::class, 'formulir'])->name('respon.formulir');
    Route::get('/formulir/draft', [ResponController::class, 'formulirDraft'])->name('respon.formulir-draft');
    Route::get('/formulir/final', [ResponController::class, 'formulirFinal'])->name('respon.formulir-final');
    Route::get('/npe-bc33/{kodeDokumen}/{tanggalDokumen}', [ResponController::class, 'npeBc33'])
        ->where('kodeDokumen', '[0-9A-Za-z]+')
        ->where('tanggalDokumen', '[0-9-]+')
        ->name('respon.npe-bc33');
    Route::get('/download', [ResponController::class, 'download'])->name('respon.download');
});
