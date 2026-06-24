<?php

use App\Http\Controllers\Api\V1\PibController;
use Illuminate\Support\Facades\Route;

/**
 * Fase 3 — Submit PIB (outbound) + Fase 6 list/detail/notul + Wizard (draft/update).
 */
Route::prefix('pib')->group(function () {
    // Wizard (9-step form)
    Route::post('/draft', [PibController::class, 'draft'])->name('pib.draft');
    Route::patch('/{id}', [PibController::class, 'updateDraft'])->name('pib.update-draft');

    // Import-by-AJU — sync dokumen CEISA portal ke DB lokal + recovery
    // state divergence (submit lokal 'failed' tapi BC 'perekaman dokumen').
    Route::post('/sync-by-aju', [PibController::class, 'syncByAju'])->name('pib.sync-by-aju');

    // Fase 3 — Submit ke BC
    Route::post('/submit', [PibController::class, 'submit'])->name('pib.submit');
    Route::post('/{id}/retry', [PibController::class, 'retry'])->name('pib.retry');

    // Fase 6 — List/detail/notul
    Route::get('/', [PibController::class, 'index'])->name('pib.index');
    Route::get('/notul', [PibController::class, 'notul'])->name('pib.notul');
    Route::get('/{id}', [PibController::class, 'show'])->name('pib.show');
});