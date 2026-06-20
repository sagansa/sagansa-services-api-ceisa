<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\StatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller untuk endpoint Status dokumen pabean (v2 unified endpoint).
 *
 * v2 CEISA OpenAPI memperkenalkan endpoint status yang unified untuk semua
 * dokumen pabean (PIB/BC 2.0, BC 2.3, BC 3.0, TPB, dll).
 *
 *   GET /v1/status/{nomorAju}     → history status + respon by nomorAju
 *   GET /v1/status?nitku=<NITKU>  → list dokumen belum diambil by NITKU
 */
class StatusController extends Controller
{
    public function __construct(protected StatusService $status)
    {
    }

    /**
     * GET /v1/status/{nomorAju} — status by nomorAju.
     *
     * Atau GET /v1/status?nitku=<NITKU> — status by NITKU (jika nomorAju kosong).
     */
    public function show(Request $request, ?string $nomorAju = null): JsonResponse
    {
        // Jika nomorAju tidak diberikan via path, cek query nitku.
        if (empty($nomorAju)) {
            $nitku = trim((string) $request->query('nitku', ''));
            if ($nitku === '') {
                return response()->json([
                    'success' => false,
                    'error'   => 'nomorAju (path) atau nitku (query) wajib diisi.',
                ], 422);
            }

            $result = $this->status->getByNitku($nitku);

            return response()->json($result, $result['success'] ? 200 : 502);
        }

        $nomorAju = trim($nomorAju);
        if ($nomorAju === '') {
            return response()->json([
                'success' => false,
                'error'   => 'nomorAju wajib diisi.',
            ], 422);
        }

        $result = $this->status->getByNomorAju($nomorAju);

        return response()->json($result, $result['success'] ? 200 : 502);
    }
}