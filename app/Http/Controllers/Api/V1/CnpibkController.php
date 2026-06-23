<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\CnpibkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CNPIBK Controller — Barang kiriman (e-commerce / postal items).
 *
 * Enterprise tier only. Membungkus 21 endpoint CNPIBK di gateway BC.
 *
 * Dikelompokkan per kategori untuk kemudahan navigasi:
 *   /v1/cnpibk/kirim/*       → kirim data (impor, ekspor, pkbk)
 *   /v1/cnpibk/bc14/*        → BC 1.4 operasi
 *   /v1/cnpibk/billing/*     → billing konsolidasi
 *   /v1/cnpibk/respon/*      → tarik respon
 *   /v1/cnpibk/xray/*        → X-ray
 *   /v1/cnpibk/referensi/*   → e-catalogue, e-invoice
 */
class CnpibkController extends Controller
{
    public function __construct(protected CnpibkService $service)
    {
    }

    // ===== Kirim Data =====

    public function kirimImpor(Request $request): JsonResponse
    {
        return $this->postAction($request, 'kirimImpor');
    }

    public function kirimEkspor(Request $request): JsonResponse
    {
        return $this->postAction($request, 'kirimEkspor');
    }

    public function kirimPkbk(Request $request): JsonResponse
    {
        return $this->postAction($request, 'kirimPkbk');
    }

    // ===== BC 1.1 / BC 1.4 =====

    public function updateBc11(Request $request): JsonResponse
    {
        return $this->postAction($request, 'updateBc11');
    }

    public function kirimBc14(Request $request): JsonResponse
    {
        return $this->postAction($request, 'kirimBc14');
    }

    public function pecahPosBc14(Request $request): JsonResponse
    {
        return $this->postAction($request, 'pecahPosBc14');
    }

    public function statusBc14(string $noAju): JsonResponse
    {
        return $this->getAction('statusBc14', $noAju);
    }

    // ===== Billing & Referensi =====

    public function tarikBilling(): JsonResponse
    {
        return $this->simpleGet('tarikBilling');
    }

    public function tarikBillingByKode(string $kodeBilling): JsonResponse
    {
        return $this->getAction('tarikBillingByKode', $kodeBilling);
    }

    public function kirimDaftarTertentu(Request $request): JsonResponse
    {
        return $this->postAction($request, 'kirimDaftarTertentu');
    }

    public function eCatalogue(): JsonResponse
    {
        return $this->simpleGet('eCatalogue');
    }

    public function eInvoice(): JsonResponse
    {
        return $this->simpleGet('eInvoice');
    }

    // ===== Respon =====

    public function tarikRespon(string $nomorAju): JsonResponse
    {
        return $this->getAction('tarikRespon', $nomorAju);
    }

    public function responByAju(string $nomorAju): JsonResponse
    {
        return $this->getAction('responByAju', $nomorAju);
    }

    public function responByStatus(string $status): JsonResponse
    {
        return $this->getAction('responByStatus', $status);
    }

    public function eksporByDokumen(string $nomorAju): JsonResponse
    {
        return $this->getAction('eksporByDokumen', $nomorAju);
    }

    public function eksporByTglDaftar(string $tanggal): JsonResponse
    {
        return $this->getAction('eksporByTglDaftar', $tanggal);
    }

    public function eksporByTglSubmit(string $tanggal): JsonResponse
    {
        return $this->getAction('eksporByTglSubmit', $tanggal);
    }

    // ===== X-Ray =====

    public function addFotoXray(Request $request): JsonResponse
    {
        return $this->postAction($request, 'addFotoXray');
    }

    public function getFotoXray(string $id): JsonResponse
    {
        return $this->getAction('getFotoXray', $id);
    }

    public function kirimFotoXray(Request $request): JsonResponse
    {
        return $this->postAction($request, 'kirimFotoXray');
    }

    // ===== Helpers =====

    protected function postAction(Request $request, string $method): JsonResponse
    {
        $payload = $request->all();
        $result = $this->service->{$method}($payload);

        return $result['success']
            ? ApiResponse::success($result['data'], 'CNPIBK: ' . $method)
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    protected function getAction(string $method, string $param): JsonResponse
    {
        $result = $this->service->{$method}($param);

        return $result['success']
            ? ApiResponse::success($result['data'], 'CNPIBK: ' . $method)
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    protected function simpleGet(string $method): JsonResponse
    {
        $result = $this->service->{$method}();

        return $result['success']
            ? ApiResponse::success($result['data'], 'CNPIBK: ' . $method)
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }
}
