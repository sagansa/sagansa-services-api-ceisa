<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\OssService;
use Illuminate\Http\JsonResponse;

/**
 * OSS Controller — lookup data perusahaan via NIB.
 *
 * GET /v1/oss/nib/{nib} → return nama, alamat, NPWP, dll.
 * Dipakai di form PIB step "Entitas" untuk auto-fill Pemilik Barang
 * dan NPWP Pemusatan.
 */
class OssController extends Controller
{
    public function __construct(protected OssService $service)
    {
    }

    /** GET /v1/oss/nib/{nib} */
    public function lookup(string $nib): JsonResponse
    {
        $result = $this->service->lookupNib($nib);

        if (!$result['success']) {
            return ApiResponse::error(
                $result['error'] ?? 'NIB lookup failed',
                $result['status_code'] ?? 502,
            );
        }

        return ApiResponse::success($result['data'], 'NIB lookup');
    }
}
