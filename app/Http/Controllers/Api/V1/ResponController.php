<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\ResponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Respon Controller — PDF retrieval dari gateway CEISA 4.0.
 *
 * Membungkus 7 endpoint PDF di gateway BC (respon-controller):
 *
 *   GET /v1/respon/pdf?kodeRespon=&nomorAju=
 *   GET /v1/respon/billing?kodeBilling=
 *   GET /v1/respon/formulir?nomorAju=
 *   GET /v1/respon/formulir/draft?nomorAju=
 *   GET /v1/respon/formulir/final?nomorAju=
 *   GET /v1/respon/npe-bc33/{kodeDokumen}/{tanggalDokumen}?kodeGudang=
 *   GET /v1/respon/download?path=
 *
 * Response: PDF binary (Content-Type: application/pdf) saat sukses, JSON
 * error saat gagal. Pakai query param ?inline=1 untuk preview di browser.
 */
class ResponController extends Controller
{
    public function __construct(protected ResponService $service)
    {
    }

    /** GET /v1/respon/pdf — PDF Respon. */
    public function pdf(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'kodeRespon' => ['required', 'string', 'max:16'],
            'nomorAju'   => ['required', 'string', 'max:32'],
        ]);

        return $this->stream(
            $this->service->pdf($data['kodeRespon'], $data['nomorAju']),
            $request->boolean('inline'),
        );
    }

    /** GET /v1/respon/billing — PDF Billing/tunggakan. */
    public function billing(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'kodeBilling' => ['required', 'string', 'max:32'],
        ]);

        return $this->stream(
            $this->service->billing($data['kodeBilling']),
            $request->boolean('inline'),
        );
    }

    /** GET /v1/respon/formulir — PDF Formulir resmi. */
    public function formulir(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'nomorAju' => ['required', 'string', 'max:32'],
        ]);

        return $this->stream(
            $this->service->formulir($data['nomorAju']),
            $request->boolean('inline'),
        );
    }

    /** GET /v1/respon/formulir/draft — PDF Formulir draft. */
    public function formulirDraft(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'nomorAju' => ['required', 'string', 'max:32'],
        ]);

        return $this->stream(
            $this->service->formulirDraft($data['nomorAju']),
            $request->boolean('inline'),
        );
    }

    /** GET /v1/respon/formulir/final — PDF Formulir final. */
    public function formulirFinal(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'nomorAju' => ['required', 'string', 'max:32'],
        ]);

        return $this->stream(
            $this->service->formulirFinal($data['nomorAju']),
            $request->boolean('inline'),
        );
    }

    /** GET /v1/respon/npe-bc33/{kodeDokumen}/{tanggalDokumen} — NPE BC 3.3. */
    public function npeBc33(Request $request, string $kodeDokumen, string $tanggalDokumen): Response|JsonResponse
    {
        $data = $request->validate([
            'kodeGudang' => ['required', 'string', 'max:16'],
        ]);

        return $this->stream(
            $this->service->npeBc33($kodeDokumen, $tanggalDokumen, $data['kodeGudang']),
            $request->boolean('inline'),
        );
    }

    /** GET /v1/respon/download?path= — Download PDF Respon by path. */
    public function download(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:512'],
        ]);

        return $this->stream(
            $this->service->download($data['path']),
            $request->boolean('inline'),
        );
    }

    // -----------------------------------------------------------------------
    // Helper
    // -----------------------------------------------------------------------

    /**
     * Stream PDF binary ke client, atau JSON error jika gagal.
     *
     * @param array $result Hasil ResponService (success, content, filename, ...).
     * @param bool  $inline true=preview browser, false=force download.
     */
    protected function stream(array $result, bool $inline): Response|JsonResponse
    {
        if (!$result['success']) {
            return ApiResponse::error(
                $result['error'] ?? 'Gagal mengambil PDF dari gateway BC.',
                $result['status_code'] ?: 502,
                meta: ['debug' => ['gateway_url' => $result['endpoint'] ?? null]],
            );
        }

        return ApiResponse::file(
            content: $result['content'],
            filename: $result['filename'],
            mimeType: $result['content_type'] ?: 'application/pdf',
            inline: $inline,
        );
    }
}
