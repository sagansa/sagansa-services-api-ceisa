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
     */
    public function test(): JsonResponse
    {
        try {
            $client = app(\App\Services\CeisaClient::class);
            // Coba GET endpoint health/status di gateway (bila tersedia)
            $response = $client->get('/');

            return response()->json([
                'status' => 'ok',
                'gateway' => $this->service->getGatewayUrl(),
                'response_code' => $response['status'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'gateway' => $this->service->getGatewayUrl(),
                'error' => $e->getMessage(),
            ], 502);
        }
    }
}
