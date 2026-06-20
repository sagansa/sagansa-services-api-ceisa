<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ManifesDocument;
use App\Services\ManifesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller untuk endpoint Manifes (openapi-manifes, CEISA 4.0).
 *
 * Fokus PRD: GET status by nomorAju.
 * Bonus: list/detail dari cache DB, draft/kirim/rekon, inward/outward.
 */
class ManifesController extends Controller
{
    public function __construct(protected ManifesService $manifes)
    {
    }

    /**
     * GET /v1/manifes/status/{nomorAju}
     *
     * Ambil history status & respon manifes dari gateway BC (live), sekaligus
     * persist ke DB untuk audit + cache tampilan mobile.
     */
    public function status(string $nomorAju): JsonResponse
    {
        $nomorAju = trim($nomorAju);

        if ($nomorAju === '') {
            return response()->json([
                'success' => false,
                'error'   => 'nomorAju wajib diisi.',
            ], 422);
        }

        $result = $this->manifes->getStatus($nomorAju);

        return response()->json($result, $result['success'] ? 200 : 502);
    }

    /**
     * GET /v1/manifes — list manifes (dari cache DB lokal).
     */
    public function index(Request $request): JsonResponse
    {
        $query = ManifesDocument::query();

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($kantor = $request->get('kode_kantor')) {
            $query->where('kode_kantor', $kantor);
        }
        if ($from = $request->get('from')) {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->get('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json($query->latest()->paginate($perPage));
    }

    /**
     * GET /v1/manifes/{nomorAju} — detail by nomor_aju (cache DB).
     */
    public function show(string $nomorAju): JsonResponse
    {
        $doc = ManifesDocument::findByNomorAju($nomorAju);

        if (!$doc) {
            return response()->json([
                'success' => false,
                'error'   => 'Manifes tidak ditemukan. Gunakan GET /v1/manifes/status/{nomorAju} untuk ambil live.',
            ], 404);
        }

        return response()->json($doc);
    }

    /**
     * POST /v1/manifes/draft — drafting manifes NVOCC ke gateway BC.
     */
    public function draft(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nomorAju'             => ['required', 'string', 'max:32'],
            'kodeKantor'           => ['nullable', 'string', 'max:16'],
            'jenisManifes'         => ['nullable', 'string', 'max:8'],
            'nomorVoyage'          => ['nullable', 'string', 'max:64'],
            'namaSaranaPengangkut' => ['nullable', 'string', 'max:255'],
            // Sisanya bebas — teruskan ke gateway.
        ]);

        $response = $this->manifes->draft($data);

        return response()->json([
            'success'     => $response['status'] >= 200 && $response['status'] < 300,
            'status_code' => $response['status'],
            'nomor_aju'   => $data['nomorAju'],
            'raw'         => $response['body'],
        ], $response['status'] >= 200 && $response['status'] < 300 ? 200 : 502);
    }

    /**
     * POST /v1/manifes/kirim — kirim draft READY.
     */
    public function kirim(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nomorAju' => ['required', 'string', 'max:32'],
        ]);

        $response = $this->manifes->kirim($data);

        return response()->json([
            'success'     => $response['status'] >= 200 && $response['status'] < 300,
            'status_code' => $response['status'],
            'nomor_aju'   => $data['nomorAju'],
            'raw'         => $response['body'],
        ], $response['status'] >= 200 && $response['status'] < 300 ? 200 : 502);
    }

    /**
     * POST /v1/manifes/rekon — rekonsiliasi pecah pos.
     */
    public function rekon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nomorAju' => ['required', 'string', 'max:32'],
        ]);

        $response = $this->manifes->rekon($data);

        return response()->json([
            'success'     => $response['status'] >= 200 && $response['status'] < 300,
            'status_code' => $response['status'],
            'nomor_aju'   => $data['nomorAju'],
            'raw'         => $response['body'],
        ], $response['status'] >= 200 && $response['status'] < 300 ? 200 : 502);
    }

    /**
     * GET /v1/manifes/bc11?nomorAju=...
     */
    public function bc11(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nomorAju' => ['required', 'string', 'max:32'],
        ]);

        $response = $this->manifes->bc11($data['nomorAju']);

        return response()->json($response, $response['status'] >= 200 && $response['status'] < 300 ? 200 : 502);
    }

    /**
     * GET /v1/manifes/inward?kodeKantor=&nomorBl=&tanggalBl=&nama=
     *
     * v2 endpoint: GET /manifes-bc11 — Inward Manifes BC 1.1.
     */
    public function inward(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kodeKantor' => ['required', 'string', 'max:16'],
            'nomorBl'    => ['required', 'string', 'max:64'],
            'tanggalBl'  => ['required', 'string', 'max:16'],
            'nama'       => ['nullable', 'string', 'max:255'],
        ]);

        $response = $this->manifes->inward(
            $data['kodeKantor'],
            $data['nomorBl'],
            $data['tanggalBl'],
            $data['nama'] ?? null,
        );

        return response()->json($response, $response['status'] >= 200 && $response['status'] < 300 ? 200 : 502);
    }

    /**
     * GET /v1/manifes/outward?kodeKantor=&nomorBl=&tanggalBl=
     *
     * Catatan: v2 OpenAPI tidak expose endpoint outward terpisah.
     */
    public function outward(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kodeKantor' => ['required', 'string', 'max:16'],
            'nomorBl'    => ['required', 'string', 'max:64'],
            'tanggalBl'  => ['required', 'string', 'max:16'],
        ]);

        $response = $this->manifes->outward($data['kodeKantor'], $data['nomorBl'], $data['tanggalBl']);

        return response()->json($response, $response['status'] >= 200 && $response['status'] < 300 ? 200 : 502);
    }

    /**
     * GET /v1/manifes/respon-pdf?kodeRespon=&nomorAju= — PDF Respon (v2 endpoint).
     *
     * v2: GET /respon/pdf?kodeRespon=&nomorAju=
     */
    public function responPdf(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kodeRespon' => ['required', 'string', 'max:64'],
            'nomorAju'   => ['required', 'string', 'max:26'],
        ]);

        $response = $this->manifes->responPdf($data['kodeRespon'], $data['nomorAju']);

        return response()->json($response, $response['status'] >= 200 && $response['status'] < 300 ? 200 : 502);
    }
}