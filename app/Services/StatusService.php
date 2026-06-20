<?php

namespace App\Services;

use App\Models\CeisaStatusHistory;
use App\Models\PibDocument;
use Illuminate\Support\Facades\Log;

/**
 * Status Service — CEISA 4.0 OpenAPI v2 (unified status endpoint).
 *
 * v2 memperkenalkan endpoint status yang unified untuk semua dokumen pabean
 * (PIB/BC 2.0, BC 2.3, BC 3.0, TPB, dll):
 *
 *   GET /status?idPerusahaan=<NITKU>   → list dokumen BELUM diambil by NITKU
 *   GET /status/{nomorAju}              → history status + respon by nomorAju
 *
 * Response v2 /status/{nomorAju} (lihat example OpenAPI v2):
 * {
 *   "dataStatus": [{ nomorAju, kodeStatus, nomorDaftar, tanggalDaftar,
 *                    waktuStatus, keterangan }],
 *   "dataRespon": [{ nomorAju, kodeRespon, nomorDaftar, tanggalDaftar,
 *                    nomorRespon, tanggalRespon, waktuRespon, waktuStatus,
 *                    keterangan, pesan:[{uraian1, uraian2}], Pdf:"base64" }]
 * }
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: status-controller.
 *
 * Semua request otomatis menyertakan auth (beacukai-api-key + Bearer) via CeisaClient.
 */
class StatusService
{
    public function __construct(protected CeisaClient $client)
    {
    }

    /**
     * GET /status/{nomorAju} — history status & respon by nomorAju.
     *
     * Hanya dapat mengambil dokumen yang statusnya Host to Host.
     *
     * @param string $nomorAju Nomor Aju dokumen (maksimal 26 karakter).
     * @return array{
     *     success: bool,
     *     status_code: int,
     *     nomor_aju: string,
     *     data_status: array,
     *     data_respon: array,
     *     latest_status: ?string,
     *     raw: array,
     *     error: ?string
     * }
     */
    public function getByNomorAju(string $nomorAju): array
    {
        $path = $this->endpoint('status_by_aju', ['nomorAju' => $nomorAju]);

        try {
            $response = $this->client->get($path, [], 'manifes');
            $code = $response['status'];
            $body = $response['body'];

            $dataStatus = $body['dataStatus'] ?? [];
            $dataRespon = $body['dataRespon'] ?? [];
            $latestStatus = $this->extractLatestStatus($dataStatus);

            // Persist ke ceisa_status_histories jika ada PIB document terkait.
            $this->persistStatusHistory($nomorAju, $dataStatus, $dataRespon);

            $success = $code >= 200 && $code < 300;

            return [
                'success'        => $success,
                'status_code'    => $code,
                'nomor_aju'      => $nomorAju,
                'data_status'    => $dataStatus,
                'data_respon'    => $dataRespon,
                'latest_status'  => $latestStatus,
                'raw'            => $body,
                'error'          => $success ? null : ($body['message'] ?? "HTTP {$code}"),
            ];
        } catch (\Throwable $e) {
            Log::error('StatusService getByNomorAju failed', [
                'nomor_aju' => $nomorAju,
                'error'     => $e->getMessage(),
            ]);

            return [
                'success'        => false,
                'status_code'    => 0,
                'nomor_aju'      => $nomorAju,
                'data_status'    => [],
                'data_respon'    => [],
                'latest_status'  => null,
                'raw'            => [],
                'error'          => $e->getMessage(),
            ];
        }
    }

    /**
     * GET /status?idPerusahaan= — list dokumen BELUM diambil by NITKU.
     *
     * Endpoint ini mengembalikan riwayat status dan respon dari dokumen yang
     * belum diambil, berdasarkan NITKU perusahaan.
     *
     * @param string $nitku NITKU perusahaan.
     * @return array{
     *     success: bool,
     *     status_code: int,
     *     nitku: string,
     *     data: array,
     *     raw: array,
     *     error: ?string
     * }
     */
    public function getByNitku(string $nitku): array
    {
        $path = $this->endpoint('status_by_nitku');

        try {
            $response = $this->client->get($path, ['idPerusahaan' => $nitku], 'manifes');
            $code = $response['status'];
            $body = $response['body'];
            $success = $code >= 200 && $code < 300;

            return [
                'success'     => $success,
                'status_code' => $code,
                'nitku'       => $nitku,
                'data'        => $body['data'] ?? $body,
                'raw'         => $body,
                'error'       => $success ? null : ($body['message'] ?? "HTTP {$code}"),
            ];
        } catch (\Throwable $e) {
            Log::error('StatusService getByNitku failed', [
                'nitku' => $nitku,
                'error' => $e->getMessage(),
            ]);

            return [
                'success'     => false,
                'status_code' => 0,
                'nitku'       => $nitku,
                'data'        => [],
                'raw'         => [],
                'error'       => $e->getMessage(),
            ];
        }
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Resolve endpoint config key dan substitute placeholder {nomorAju}.
     */
    protected function endpoint(string $key, array $params = []): string
    {
        $path = (string) config("ceisa.endpoints.{$key}", "/{$key}");

        foreach ($params as $k => $v) {
            $path = str_replace('{' . $k . '}', (string) $v, $path);
        }

        return $path;
    }

    /**
     * Ambil status terbaru dari list dataStatus response v2.
     */
    protected function extractLatestStatus(array $dataStatus): ?string
    {
        if (empty($dataStatus)) {
            return null;
        }
        $latest = $dataStatus[0] ?? null;
        if (!is_array($latest)) {
            return null;
        }

        return $latest['kodeStatus']
            ?? $latest['statusRespon']
            ?? $latest['status']
            ?? null;
    }

    /**
     * Persist ringkasan status ke ceisa_status_histories jika ada PIB terkait.
     *
     * Mencari PibDocument berdasarkan aju_number, lalu mencatat setiap entry
     * dataStatus sebagai CeisaStatusHistory (best-effort, tidak throw).
     */
    protected function persistStatusHistory(string $nomorAju, array $dataStatus, array $dataRespon): void
    {
        try {
            $pib = PibDocument::where('aju_number', $nomorAju)->first();
            if (!$pib) {
                return; // Bukan PIB atau belum tersimpan di DB; skip persist.
            }

            foreach ($dataStatus as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $kode = $entry['kodeStatus'] ?? null;
                $waktu = $entry['waktuStatus'] ?? null;
                if (!$kode || !$waktu) {
                    continue;
                }

                CeisaStatusHistory::firstOrCreate(
                    [
                        'pib_document_id' => $pib->id,
                        'status'          => $kode,
                        'received_at'     => $waktu,
                    ],
                    [
                        'urgency'     => $this->classifyUrgency((string) $kode),
                        'raw_payload' => $entry,
                    ],
                );
            }
        } catch (\Throwable $e) {
            Log::warning('StatusService: gagal persist status history', [
                'nomor_aju' => $nomorAju,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Klasifikasi urgency berdasarkan kode/keterangan status (PRD 2.2).
     */
    protected function classifyUrgency(string $kode): string
    {
        $kodeUpper = strtoupper($kode);
        $urgent = (array) config('ceisa.urgency.urgent', []);
        foreach ($urgent as $keyword) {
            if (str_contains($kodeUpper, strtoupper($keyword))) {
                return 'urgent';
            }
        }

        return 'normal';
    }
}