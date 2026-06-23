<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\GateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Gate Controller — Manajemen gate TPB.
 *
 * Membungkus 6 endpoint gate di gateway BC (gate-controller):
 *
 *   GET  /v1/gate/dokumen/{nomorAju}        → data dokumen gate-in
 *   POST /v1/gate/kemasan/in                → gate-in kemasan
 *   POST /v1/gate/kemasan/out               → gate-out kemasan
 *   POST /v1/gate/kontainer/in              → gate-in kontainer
 *   POST /v1/gate/rekam/bongkar             → rekam hasil bongkar
 *   POST /v1/gate/rekam/stuffing            → rekam hasil stuffing
 */
class GateController extends Controller
{
    public function __construct(protected GateService $service)
    {
    }

    /** GET /v1/gate/dokumen/{nomorAju} */
    public function dokumen(string $nomorAju): JsonResponse
    {
        $result = $this->service->dokumen($nomorAju);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Data dokumen gate')
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    /** POST /v1/gate/kemasan/in */
    public function kemasanIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nomorAju' => ['required', 'string', 'max:64'],
            'jumlahKemasan' => ['nullable', 'integer', 'min:1'],
            'kodeJenisKemasan' => ['nullable', 'string', 'max:8'],
        ]);

        $result = $this->service->kemasanIn($data);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Gate-in kemasan berhasil')
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    /** POST /v1/gate/kemasan/out */
    public function kemasanOut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nomorAju' => ['required', 'string', 'max:64'],
            'jumlahKemasan' => ['nullable', 'integer', 'min:1'],
            'kodeJenisKemasan' => ['nullable', 'string', 'max:8'],
        ]);

        $result = $this->service->kemasanOut($data);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Gate-out kemasan berhasil')
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    /** POST /v1/gate/kontainer/in */
    public function kontainerIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nomorAju' => ['required', 'string', 'max:64'],
            'nomorKontainer' => ['required', 'string', 'max:16'],
            'ukuranKontainer' => ['nullable', 'string', 'max:8'],
        ]);

        $result = $this->service->kontainerIn($data);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Gate-in kontainer berhasil')
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    /** POST /v1/gate/rekam/bongkar */
    public function rekamBongkar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nomorAju' => ['required', 'string', 'max:64'],
            'jumlahBongkar' => ['nullable', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:16'],
        ]);

        $result = $this->service->rekamBongkar($data);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Rekam bongkar berhasil')
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }

    /** POST /v1/gate/rekam/stuffing */
    public function rekamStuffing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nomorAju' => ['required', 'string', 'max:64'],
            'jumlahStuffing' => ['nullable', 'numeric', 'min:0'],
            'satuan' => ['nullable', 'string', 'max:16'],
        ]);

        $result = $this->service->rekamStuffing($data);

        return $result['success']
            ? ApiResponse::success($result['data'], 'Rekam stuffing berhasil')
            : ApiResponse::error($result['error'] ?? 'Gagal', $result['status_code'] ?: 502);
    }
}
