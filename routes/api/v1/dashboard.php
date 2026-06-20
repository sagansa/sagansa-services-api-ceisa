<?php

use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

/**
 * Fase 6 — Dasbor: logs & settings.
 */
Route::get('/api-logs', [DashboardController::class, 'apiLogs'])->name('dashboard.api-logs');
Route::get('/notification-logs', [DashboardController::class, 'notificationLogs'])->name('dashboard.notification-logs');
Route::get('/notification-settings', [DashboardController::class, 'notificationSettings'])->name('dashboard.notification-settings.index');
Route::patch('/notification-settings/{channel}', [DashboardController::class, 'updateNotificationSettings'])->name('dashboard.notification-settings.update');