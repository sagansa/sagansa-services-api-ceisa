<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\ReferensiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Referensi & Kurs Controller — master data BC.
 *
 * Membungkus 5 endpoint referensi/kurs di gateway BC (referensi-controller):
 *
 *   GET /v1/referensi/pelabuhan-dalam/{kodeKantor}
 *   GET /v1/referensi/pelabuhan-luar/{kata}
 *   GET /v1/referensi/tps-gudang/{kodeKantor}
 *   GET /v1/kurs/{kodeValuta}
 *   GET /v1/referensi/pungutan/{nomorAju}    (alias generate-pungutan/20)
 *
 * Dipakai operator saat: isi PIB (cari pelabuhan/TPS), cek kurs NDPBM,
 * hitung pungutan setelah PIB terdaftar.
 */
class ReferensiController extends Controller
{
    public function __construct(protected ReferensiService $service)
    {
    }

    /** GET /v1/referensi/pelabuhan-dalam/{kodeKantor} */
    public function pelabuhanDalam(string $kodeKantor): JsonResponse
    {
        $result = $this->service->pelabuhanDalam($kodeKantor);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Pelabuhan dalam negeri')
            : ApiResponse::error($result['error'] ?? 'Gagal mengambil data', $result['status_code'] ?: 502);
    }

    /** GET /v1/referensi/pelabuhan-luar/{kata} */
    public function pelabuhanLuar(string $kata): JsonResponse
    {
        $result = $this->service->pelabuhanLuar($kata);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Pelabuhan luar negeri')
            : ApiResponse::error($result['error'] ?? 'Gagal mengambil data', $result['status_code'] ?: 502);
    }

    /** GET /v1/referensi/tps-gudang/{kodeKantor} */
    public function tpsGudang(string $kodeKantor): JsonResponse
    {
        $result = $this->service->tpsGudang($kodeKantor);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Gudang TPS')
            : ApiResponse::error($result['error'] ?? 'Gagal mengambil data', $result['status_code'] ?: 502);
    }

    /** GET /v1/kurs/{kodeValuta} */
    public function kurs(string $kodeValuta): JsonResponse
    {
        $result = $this->service->kurs($kodeValuta);

        return $result['success']
            ? ApiResponse::success($result['data'], "Kurs NDPBM {$kodeValuta}")
            : ApiResponse::error($result['error'] ?? 'Gagal mengambil kurs', $result['status_code'] ?: 502);
    }

    /** GET /v1/referensi/pungutan/{nomorAju} */
    public function pungutan(string $nomorAju): JsonResponse
    {
        $result = $this->service->generatePungutan($nomorAju);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Pungutan BC 2.0')
            : ApiResponse::error($result['error'] ?? 'Gagal generate pungutan', $result['status_code'] ?: 502);
    }
}
