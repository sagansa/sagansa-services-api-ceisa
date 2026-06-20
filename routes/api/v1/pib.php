<?php

use App\Http\Controllers\Api\V1\PibController;
use Illuminate\Support\Facades\Route;

/**
 * Fase 3 — Submit PIB (outbound) + Fase 6 list/detail/notul.
 */
Route::prefix('pib')->group(function () {
    // Fase 3
    Route::post('/submit', [PibController::class, 'submit'])->name('pib.submit');
    Route::post('/{id}/retry', [PibController::class, 'retry'])->name('pib.retry');

    // Fase 6
    Route::get('/', [PibController::class, 'index'])->name('pib.index');
    Route::get('/notul', [PibController::class, 'notul'])->name('pib.notul');
    Route::get('/{id}', [PibController::class, 'show'])->name('pib.show');
});