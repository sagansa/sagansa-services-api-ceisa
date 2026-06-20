<?php

use App\Http\Controllers\Api\V1\CredentialController;
use Illuminate\Support\Facades\Route;

/**
 * Fase 2 — Manajemen Kredensial CEISA.
 */
Route::prefix('credentials')->group(function () {
    Route::get('/', [CredentialController::class, 'show'])->name('credentials.show');
    Route::put('/', [CredentialController::class, 'update'])->name('credentials.update');
    Route::post('/test', [CredentialController::class, 'test'])->name('credentials.test');
});