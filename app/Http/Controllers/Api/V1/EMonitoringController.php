<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\EMonitoringService;
use Illuminate\Http\JsonResponse;

/**
 * E-Monitoring Controller — Laporan H@H Service TPB.
 *
 * Enterprise tier only. Membungkus 3 endpoint e-monitoring di gateway BC.
 *
 *   GET /v1/e-monitoring/status      → status laporan
 *   GET /v1/e-monitoring/inventori   → laporan inventori
 *   GET /v1/e-monitoring/mutasi      → laporan mutasi
 */
class EMonitoringController extends Controller
{
    public function __construct(protected EMonitoringService $service)
    {
    }

    /** GET /v1/e-monitoring/status */
    public function status(): JsonResponse
    {
        $result = $this->service->statusLaporan();

        return $result['success']
            ? ApiResponse::success($result['data'], 'Status laporan e-monitoring')
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    /** GET /v1/e-monitoring/inventori */
    public function inventori(): JsonResponse
    {
        $result = $this->service->inventori();

        return $result['success']
            ? ApiResponse::success($result['data'], 'Laporan inventori TPB')
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    /** GET /v1/e-monitoring/mutasi */
    public function mutasi(): JsonResponse
    {
        $result = $this->service->mutasi();

        return $result['success']
            ? ApiResponse::success($result['data'], 'Laporan mutasi TPB')
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }
}
