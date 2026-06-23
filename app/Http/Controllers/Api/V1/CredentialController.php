<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CeisaCredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Fase 2 — Manajemen Kredensial CEISA.
 */
class CredentialController extends Controller
{
    public function __construct(protected CeisaCredentialService $service)
    {
    }

    /**
     * Ambil status kredensial (masked).
     *
     * Token akses BC didapat via pass-through dari frontend (user login BC).
     * Backend tidak mengelola OAuth2/token — karenanya response tidak menyertakan
     * field OAuth2 lagi.
     */
    public function show(): JsonResponse
    {
        $creds = $this->service->getCredentials();

        return response()->json([
            'application_id' => $creds['application_id'] ? $this->mask($creds['application_id']) : null,
            'gateway_mode' => $creds['gateway_mode'],
            'gateway_url' => $this->service->getGatewayUrl(),
            'is_configured' => $this->service->isConfigured(),
        ]);
    }

    /**
     * Mask string untuk tampilan aman (e.g. "ab****yz").
     */
    protected function mask(string $value): string
    {
        $len = strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 2) . str_repeat('*', max(4, $len - 4)) . substr($value, -2);
    }

    /**
     * Simpan / update kredensial.
     *
     * Hanya field dasar (application_id, api_key, gateway_mode). Token akses BC
     * didapat via pass-through dari frontend saat user login BC.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'application_id' => ['required', 'string', 'max:255'],
            'api_key' => ['required', 'string', 'max:512'],
            'gateway_mode' => ['required', Rule::in(['sandbox', 'production'])],
        ]);

        $this->service->updateCredentials(
            $data['application_id'],
            $data['api_key'],
            $data['gateway_mode'],
        );

        return response()->json([
            'message' => 'Kredensial berhasil disimpan (encrypted).',
            'gateway_mode' => $data['gateway_mode'],
        ]);
    }

    /**
     * Test koneksi ke gateway (Fase 2 deliverable).
     *
     * Mengecek konektivitas (bukan autentikasi): gateway dianggap "reachable"
     * bila memberi respons HTTP apa pun (termasuk 401/403/404). Hanya gagal
     * jika koneksi/SSL/DNS yang error (mis. cURL error 56 empty reply).
     */
    public function test(): JsonResponse
    {
        $client = app(\App\Services\CeisaClient::class);
        $gateway = $this->service->getGatewayUrl();
        $testPath = (string) config('ceisa.test_path', '/v1/pib/submit');

        $result = $client->ping();

        if ($result['reachable']) {
            $code = $result['status'];

            // Klasifikasi pesan berdasar status code untuk memandu user.
            $hint = match (true) {
                $code >= 200 && $code < 300 => 'Gateway merespons OK — koneksi & kredensial valid.',
                $code === 401 || $code === 403 => 'Gateway reachable tetapi menolak (Unauthorized/Forbidden). Periksa Application ID, API Key, dan whitelist IP di portal BC.',
                $code === 404 => 'Gateway reachable (404). IP kemungkinan sudah di-whitelist; path API belum tersedia atau belum diverifikasi via OpenAPI Portal.',
                $code >= 500 => 'Gateway mengalami error sisi server. Coba beberapa saat lagi.',
                default => 'Gateway reachable dengan response code ' . $code . '.',
            };

            return response()->json([
                'status' => 'ok',
                'gateway' => $gateway,
                'test_path' => $testPath,
                'response_code' => $code,
                'hint' => $hint,
            ]);
        }

        // Diagnosa failure berdasar pesan cURL/Guzzle untuk memandu troubleshooting.
        $err = (string) $result['error'];
        $hint = $this->diagnoseError($err);

        return response()->json([
            'status' => 'error',
            'gateway' => $gateway,
            'test_path' => $testPath,
            'error' => $err,
            'hint' => $hint,
        ], 502);
    }

    /**
     * Pesan panduan troubleshoot berdasarkan pola error yang umum.
     */
    protected function diagnoseError(string $error): string
    {
        return match (true) {
            str_contains($error, 'Empty reply from server'),
            str_contains($error, 'unexpected eof'),
            str_contains($error, 'error 56') => 'Gateway menutup koneksi tanpa respons HTTP. Bisa karena path yang di-hit tidak valid atau IP belum di-whitelist BC. Pastikan CEISA_TEST_PATH menunjuk ke endpoint API nyata.',
            str_contains($error, 'Connection refused'),
            str_contains($error, 'error 7') => 'Koneksi ditolak. Cek koneksi jaringan/VPN.',
            str_contains($error, 'Could not resolve host'),
            str_contains($error, 'error 6') => 'DNS gagal. Cek koneksi internet atau konfigurasi DNS.',
            str_contains($error, 'timed out'),
            str_contains($error, 'error 28') => 'Timeout. Naikkan CEISA_HTTP_TIMEOUT / CEISA_CONNECT_TIMEOUT atau cek jaringan.',
            str_contains($error, 'SSL'),
            str_contains($error, 'certificate') => 'Masalah sertifikat SSL. Pastikan CA bundle PHP/cURL terbaru (composer update atau set CURL_CA_BUNDLE).',
            default => 'Gangguan koneksi ke gateway. Lihat log aplikasi (storage/logs) untuk detail.',
        };
    }
}
