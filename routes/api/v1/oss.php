<?php

use App\Http\Controllers\Api\V1\OssController;
use Illuminate\Support\Facades\Route;

/**
 * OSS/RBA — lookup data perusahaan via NIB.
 *
 * GET /v1/oss/nib/{nib} → return nama, alamat, NPWP perusahaan.
 * Dipakai di form PIB Entitas untuk auto-fill Pemilik Barang & NPWP Pemusatan.
 */
Route::prefix('oss')->group(function () {
    Route::get('/nib/{nib}', [OssController::class, 'lookup'])
        ->where('nib', '[0-9]+')
        ->name('oss.nib-lookup');
});
