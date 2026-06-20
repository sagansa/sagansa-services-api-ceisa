<?php

use App\Http\Controllers\Api\V1\ManifesController;
use Illuminate\Support\Facades\Route;

/**
 * Manifes (openapi-manifes, CEISA 4.0) — VERIFIED endpoints.
 *
 * Sumber: OpenAPI Portal BC — API "openapi-manifes".
 * Focus PRD: GET status by nomorAju.
 */
Route::prefix('manifes')->group(function () {
    // === FOCUS: Status ===
    Route::get('/status/{nomorAju}', [ManifesController::class, 'status'])->name('manifes.status');

    // === Write flow (draft → kirim → rekon) ===
    Route::post('/draft', [ManifesController::class, 'draft'])->name('manifes.draft');
    Route::post('/kirim', [ManifesController::class, 'kirim'])->name('manifes.kirim');
    Route::post('/rekon', [ManifesController::class, 'rekon'])->name('manifes.rekon');

    // === Read flow ===
    Route::get('/bc11', [ManifesController::class, 'bc11'])->name('manifes.bc11');
    Route::get('/inward', [ManifesController::class, 'inward'])->name('manifes.inward');
    Route::get('/outward', [ManifesController::class, 'outward'])->name('manifes.outward');
    Route::get('/respon-pdf', [ManifesController::class, 'responPdf'])->name('manifes.respon-pdf');

    // === Cache DB (list/detail) ===
    Route::get('/', [ManifesController::class, 'index'])->name('manifes.index');
    Route::get('/{nomorAju}', [ManifesController::class, 'show'])
        ->where('nomorAju', '[0-9]+')
        ->name('manifes.show');
});