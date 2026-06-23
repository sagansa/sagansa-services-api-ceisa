<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\FileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File as FileRule;

/**
 * File Controller — Upload dokumen pendukung ke gateway CEISA 4.0.
 *
 * Membungkus 3 endpoint file di gateway BC (file-controller):
 *
 *   POST /v1/file/barang           → upload file barang (detil items)
 *   POST /v1/file/dokumen          → upload file dokumen (invoice, BL, PL)
 *   POST /v1/file/dokap-npd        → upload file DOKAP/NPD
 *
 * Accept multipart/form-data (file + optional form fields).
 */
class FileController extends Controller
{
    public function __construct(protected FileService $service)
    {
    }

    /** POST /v1/file/barang */
    public function barang(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file'        => ['required', 'file', 'max:10240'], // max 10MB
            'nomorAju'    => ['nullable', 'string', 'max:64'],
            'kodeDokumen' => ['nullable', 'string', 'max:16'],
        ]);

        $file = $request->file('file');
        $result = $this->service->uploadBarang(
            binaryContent: file_get_contents($file->getRealPath()),
            filename: $file->getClientOriginalName(),
            contentType: $file->getMimeType(),
            formFields: array_filter([
                'nomorAju' => $data['nomorAju'] ?? null,
                'kodeDokumen' => $data['kodeDokumen'] ?? null,
            ]),
        );

        return $this->wrapResult($result, 'File barang');
    }

    /** POST /v1/file/dokumen */
    public function dokumen(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file'        => ['required', 'file', 'max:10240'],
            'nomorAju'    => ['nullable', 'string', 'max:64'],
            'jenisDokumen' => ['nullable', 'string', 'max:16'],
        ]);

        $file = $request->file('file');
        $result = $this->service->uploadDokumen(
            binaryContent: file_get_contents($file->getRealPath()),
            filename: $file->getClientOriginalName(),
            contentType: $file->getMimeType(),
            formFields: array_filter([
                'nomorAju' => $data['nomorAju'] ?? null,
                'jenisDokumen' => $data['jenisDokumen'] ?? null,
            ]),
        );

        return $this->wrapResult($result, 'File dokumen');
    }

    /** POST /v1/file/dokap-npd */
    public function dokapNpd(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file'     => ['required', 'file', 'max:10240'],
            'nomorAju' => ['nullable', 'string', 'max:64'],
        ]);

        $file = $request->file('file');
        $result = $this->service->uploadDokapNpd(
            binaryContent: file_get_contents($file->getRealPath()),
            filename: $file->getClientOriginalName(),
            contentType: $file->getMimeType(),
            formFields: array_filter([
                'nomorAju' => $data['nomorAju'] ?? null,
            ]),
        );

        return $this->wrapResult($result, 'File DOKAP/NPD');
    }

    /**
     * Bungkus hasil service ke ApiResponse.
     */
    protected function wrapResult(array $result, string $label): JsonResponse
    {
        $status = $result['status'] ?? 0;
        $isOk = $status >= 200 && $status < 300;

        return $isOk
            ? ApiResponse::success($result['body'], "{$label} berhasil diupload")
            : ApiResponse::error(
                $result['body']['message'] ?? "Gagal upload {$label} (HTTP {$status})",
                $status ?: 502,
                meta: ['debug' => ['raw' => $result['raw'] ?? '']],
            );
    }
}
