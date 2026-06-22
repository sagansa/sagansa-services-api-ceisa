<?php

namespace App\Http\Responses;

/**
 * Factory helper untuk response JSON konsisten di seluruh API SAGANSA CEISA.
 *
 * Format standar:
 *   {
 *     "success": bool,
 *     "message": string,
 *     "data":    mixed|null,
 *     "errors":  array|null,
 *     "meta":    array|null   (pagination, debug, dll)
 *   }
 *
 * Pakai di controller:
 *   return ApiResponse::success($data, 'Berhasil');
 *   return ApiResponse::error('Gagal', 400, $errors);
 *   return ApiResponse::proxy($result, $fallbackStatus);
 */
class ApiResponse
{
    /**
     * Response sukses standar.
     *
     * @param mixed  $data    Payload utama (array/object/scalar).
     * @param string $message Pesan ringkas (default "OK").
     * @param int    $status  HTTP status code (default 200).
     * @param array  $meta    Metadata tambahan (pagination, debug, dll).
     */
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = 200,
        ?array $meta = null,
    ): \Illuminate\Http\JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Response error standar.
     *
     * @param string $message Pesan error user-facing.
     * @param int    $status  HTTP status code (default 400).
     * @param mixed  $errors  Detail error (array validation, string, dll).
     * @param array  $meta    Metadata tambahan (debug info, dll).
     */
    public static function error(
        string $message,
        int $status = 400,
        mixed $errors = null,
        ?array $meta = null,
    ): \Illuminate\Http\JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ];
        if ($meta !== null) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Response validasi gagal (422 Unprocessable Entity).
     */
    public static function validation(\Illuminate\Validation\Validator $validator): \Illuminate\Http\JsonResponse
    {
        return self::error('Data tidak valid', 422, $validator->errors()->all());
    }

    /**
     * Response dari hasil proxy ke gateway BC.
     *
     * Menerima array hasil dari service (status, body, raw, endpoint) lalu
     * membungkus ke format standar. Saat error, sertakan meta.debug berisi
     * URL gateway + raw body BC agar mudah didiagnosis.
     *
     * @param array $result        Hasil service: {status, body, raw, endpoint?}
     * @param int   $fallbackStatus HTTP status jika $result['status']=0.
     */
    public static function proxy(array $result, int $fallbackStatus = 502): \Illuminate\Http\JsonResponse
    {
        $status = $result['status'] ?? 0;
        $body = $result['body'] ?? [];
        $raw = $result['raw'] ?? '';
        $isOk = $status >= 200 && $status < 300;

        $meta = null;
        if (!$isOk || ($raw !== '' && $raw !== '[]' && $raw !== '{}')) {
            $meta = [
                'debug' => [
                    'gateway_url' => $result['endpoint'] ?? null,
                    'raw'         => $raw,
                ],
            ];
        }

        return self::success(
            data: $body,
            message: $isOk ? 'OK' : ($body['message'] ?? "Gateway BC HTTP {$status}"),
            status: $isOk ? 200 : ($status ?: $fallbackStatus),
            meta: $meta,
        );
    }

    /**
     * Response binary file (PDF/image) langsung ke client.
     *
     * Dipakai untuk download PDF respon BC. Bypass JSON format karena ini
     * adalah binary stream.
     *
     * @param string $content   Raw binary content (PDF bytes).
     * @param string $filename  Nama file untuk Content-Disposition.
     * @param string $mimeType  MIME type (default application/pdf).
     * @param bool   $inline    true=preview di browser, false=force download.
     */
    public static function file(
        string $content,
        string $filename,
        string $mimeType = 'application/pdf',
        bool $inline = false,
    ): \Illuminate\Http\Response {
        $disposition = $inline ? 'inline' : 'attachment';

        return response($content, 200, [
            'Content-Type'        => $mimeType,
            'Content-Length'      => strlen($content),
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'Cache-Control'       => 'private, no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }
}
