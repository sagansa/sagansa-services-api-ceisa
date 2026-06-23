<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\CukaiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cukai Controller — Barang Kena Cukai (BKC) Host to Host.
 *
 * Enterprise tier only (produsen BKC: rokok, MMEA, mirasantisa).
 * Membungkus 13 endpoint openapi-cukai di gateway BC.
 *
 * Dikelompokkan per kategori:
 *   /v1/cukai/gps/*         → tracking GPS mesin produksi
 *   /v1/cukai/mesin/*       → CRUD mesin produksi
 *   /v1/cukai/produksi/*    → laporan produksi (batang/kemasan)
 *   /v1/cukai/referensi/*   → master data (jenis/tipe/kondisi/status)
 */
class CukaiController extends Controller
{
    public function __construct(protected CukaiService $service)
    {
    }

    // ===== GPS =====

    /** GET /v1/cukai/gps — list GPS. */
    public function listGps(Request $request): JsonResponse
    {
        return $this->filteredGet($request, 'listGps');
    }

    /** GET /v1/cukai/gps/{id} — detail GPS. */
    public function getGps(string $id): JsonResponse
    {
        return $this->getAction('getGpsById', $id);
    }

    /** POST /v1/cukai/gps — tambah GPS. */
    public function createGps(Request $request): JsonResponse
    {
        return $this->postAction($request, 'createGps');
    }

    // ===== Mesin =====

    /** GET /v1/cukai/mesin — list mesin. */
    public function listMesin(Request $request): JsonResponse
    {
        return $this->filteredGet($request, 'listMesin');
    }

    /** POST /v1/cukai/mesin — tambah mesin. */
    public function createMesin(Request $request): JsonResponse
    {
        return $this->postAction($request, 'createMesin');
    }

    /** PUT /v1/cukai/mesin/{id} — update mesin. */
    public function updateMesin(Request $request, string $id): JsonResponse
    {
        $result = $this->service->updateMesin($id, $request->all());

        return $result['success']
            ? ApiResponse::success($result['data'], 'Cukai: updateMesin')
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    /** DELETE /v1/cukai/mesin/{id} — hapus mesin. */
    public function deleteMesin(string $id): JsonResponse
    {
        $result = $this->service->deleteMesin($id);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Cukai: deleteMesin')
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    // ===== Produksi =====

    /** GET /v1/cukai/produksi — list laporan produksi. */
    public function listProduksi(Request $request): JsonResponse
    {
        return $this->filteredGet($request, 'listProduksi');
    }

    /** POST /v1/cukai/produksi/batang — tambah produksi batang (single). */
    public function createProduksiBatang(Request $request): JsonResponse
    {
        return $this->postAction($request, 'createProduksiBatang');
    }

    /** POST /v1/cukai/produksi/batang/batch — tambah produksi batang (batch). */
    public function createProduksiBatangBatch(Request $request): JsonResponse
    {
        return $this->postAction($request, 'createProduksiBatangBatch');
    }

    /** POST /v1/cukai/produksi/kemasan — tambah produksi kemasan (single). */
    public function createProduksiKemasan(Request $request): JsonResponse
    {
        return $this->postAction($request, 'createProduksiKemasan');
    }

    /** POST /v1/cukai/produksi/kemasan/batch — tambah produksi kemasan (batch). */
    public function createProduksiKemasanBatch(Request $request): JsonResponse
    {
        return $this->postAction($request, 'createProduksiKemasanBatch');
    }

    // ===== Referensi =====

    /** GET /v1/cukai/referensi/jenis-mesin. */
    public function refJenisMesin(): JsonResponse
    {
        return $this->simpleGet('refJenisMesin');
    }

    /** GET /v1/cukai/referensi/tipe-mesin. */
    public function refTipeMesin(): JsonResponse
    {
        return $this->simpleGet('refTipeMesin');
    }

    /** GET /v1/cukai/referensi/kondisi. */
    public function refKondisi(): JsonResponse
    {
        return $this->simpleGet('refKondisi');
    }

    /** GET /v1/cukai/referensi/status-kepemilikan. */
    public function refStatusKepemilikan(): JsonResponse
    {
        return $this->simpleGet('refStatusKepemilikan');
    }

    // ===== Helpers =====

    /**
     * POST action: kirim payload apa adanya ke service method.
     * BC cukai umumnya menerima body JSON apa adanya (pass-through).
     */
    protected function postAction(Request $request, string $method): JsonResponse
    {
        $result = $this->service->{$method}($request->all());

        return $result['success']
            ? ApiResponse::success($result['data'], 'Cukai: ' . $method)
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    /** GET action dengan 1 param path (mis. ID). */
    protected function getAction(string $method, string $param): JsonResponse
    {
        $result = $this->service->{$method}($param);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Cukai: ' . $method)
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    /** GET tanpa param (list referensi). */
    protected function simpleGet(string $method): JsonResponse
    {
        $result = $this->service->{$method}();

        return $result['success']
            ? ApiResponse::success($result['data'], 'Cukai: ' . $method)
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    /**
     * GET dengan query filters (list mesin/gps/produksi).
     * Query string diteruskan apa adanya ke gateway BC sebagai filter.
     */
    protected function filteredGet(Request $request, string $method): JsonResponse
    {
        // Hanya terima filter yang dikenal BC (mencegah param flood).
        $allowed = [
            // GPS
            'page', 'limit', 'sortField', 'sortOrder',
            'idMesinHeader', 'serialNumberDevice', 'posisi',
            // Mesin
            'merekMesin', 'keterangan', 'nomorIdentifikasi',
            'serialNumber', 'lokasi', 'kodeTipeMesin', 'kodeJenisMesin',
            'kodeKondisi', 'kodeStatusKepemilikkan', 'kodeKantor',
            // Produksi
            'idMerek', 'idProduksi',
        ];
        $filters = $request->only($allowed);

        $result = $this->service->{$method}($filters);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Cukai: ' . $method)
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }
}
