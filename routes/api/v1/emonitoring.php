<?php

use App\Http\Controllers\Api\V1\EMonitoringController;
use Illuminate\Support\Facades\Route;

/**
 * E-Monitoring H@H (CEISA 4.0 OpenAPI v2 — e-monitoring-controller).
 *
 * Enterprise tier only. Laporan inventori & mutasi TPB.
 *
 *   GET /v1/e-monitoring/status      → status laporan
 *   GET /v1/e-monitoring/inventori   → laporan inventori
 *   GET /v1/e-monitoring/mutasi      → laporan mutasi
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: e-monitoring-controller.
 */
Route::prefix('e-monitoring')->group(function () {
    Route::get('/status', [EMonitoringController::class, 'status'])->name('e-monitoring.status');
    Route::get('/inventori', [EMonitoringController::class, 'inventori'])->name('e-monitoring.inventori');
    Route::get('/mutasi', [EMonitoringController::class, 'mutasi'])->name('e-monitoring.mutasi');
});
