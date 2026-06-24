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

    /**
     * Sync satu dokumen by nomorAju: fetch status terbaru dari BC, lalu
     * upsert PibDocument lokal (shadow-document bila belum ada) + history.
     *
     * Memenuhi dua kebutuhan sekaligus:
     *  1. Import dokumen yang dibuat di CEISA portal (bukan via SAGANSA),
     *     sehingga muncul di list PIB. AJU portal berbeda format dari
     *     generator SAGANSA dan tidak tercatat di pib_documents.
     *  2. Recovery state divergence: bila lokal status='failed' (job submit
     *     lempar exception) tapi BC bilang dokumen sudah direkam/aju, status
     *     lokal di-overwrite ke status BC.
     *
     * Reuse:
     *  - getByNomorAju() (existing) → fetch /status/{nomorAju} + persist
     *    CeisaStatusHistory via persistStatusHistory().
     *  - Pattern shadow-document dari ProcessWebhookJob::resolvePibDocument():
     *    PibDocument::firstOrCreate(['aju_number'=>...]).
     *
     * Mitigasi typo AJU: record hanya di-create bila BC return sukses dan
     * ada dataStatus. AJU typo → BC 404 → tidak create sampah.
     *
     * @param  string $nomorAju Nomor Aju dokumen (6-26 karakter).
     * @return array{
     *     success: bool,
     *     pib_id: ?int,
     *     aju_number: string,
     *     status: ?string,
     *     status_label: ?string,
     *     status_slug: ?string,
     *     urgency: ?string,
     *     stage: ?string,
     *     status_code: int,
     *     error: ?string
     * }
     */
    public function syncByAju(string $nomorAju): array
    {
        $nomorAju = trim($nomorAju);

        // 1) Fetch status terbaru dari BC (sudah persist CeisaStatusHistory
        //    bila ada PibDocument terkait — lihat persistStatusHistory()).
        $result = $this->getByNomorAju($nomorAju);

        if (!$result['success'] || empty($result['data_status'])) {
            return [
                'success'     => false,
                'pib_id'      => null,
                'aju_number'  => $nomorAju,
                'status'      => null,
                'status_code' => $result['status_code'],
                'error'       => $result['error']
                    ?? 'Dokumen tidak ditemukan di BC (tidak ada dataStatus).',
            ];
        }

        $latestStatus = $result['latest_status'];
        $dataStatus = $result['data_status'];

        // 2) Upsert PibDocument (shadow-document pattern — reuse dari
        //    ProcessWebhookJob::resolvePibDocument()).
        $pib = PibDocument::firstOrCreate(
            ['aju_number' => $nomorAju],
            ['status' => 'draft'],
        );

        // 3) Ambil nomor pendaftaran dari dataStatus bila tersedia.
        $registrationNumber = $this->extractRegistrationNumber($dataStatus);

        // 4) Resolve kodeStatus BC (numeric 3-digit, mis. "001") ke badge slug
        //    (rekam/hijau/proses/failed) + label. Slug disimpan di
        //    pib_documents.status agar match theme.ts STATUS_COLORS.
        $resolved = $latestStatus !== null
            ? $this->resolveKodeProses((string) $latestStatus)
            : null;

        // 5) Update status lokal. Bila lokal 'failed' (job submit lempar
        //    exception) tapi BC kasih status valid → overwrite (recovery).
        //    Bila BC tidak kasih status, jangan overwrite status valid
        //    yang sudah ada — biarkan apa adanya.
        $updateData = ['last_webhook_at' => now()];
        if ($registrationNumber !== null) {
            $updateData['registration_number'] = $registrationNumber;
        }
        if ($resolved !== null) {
            $updateData['status'] = $resolved['slug'];
        }
        $pib->update($updateData);

        return [
            'success'      => true,
            'pib_id'       => $pib->id,
            'aju_number'   => $nomorAju,
            'status'       => $resolved['kode'] ?? $latestStatus,
            'status_label' => $resolved['label'] ?? null,
            'status_slug'  => $resolved['slug'] ?? null,
            'urgency'      => $resolved['urgency'] ?? null,
            'stage'        => $resolved['stage'] ?? null,
            'status_code'  => $result['status_code'],
            'error'        => null,
        ];
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
     * Resolve kodeStatus BC (numeric 3-digit, mis. "001") ke metadata badge.
     *
     * Memakai tabel referensi config('ceisa.kode_proses') yang berisi
     * seluruh Kode Proses CEISA 4.0 resmi (sumber: tabel Referensi Status
     * CEISA 4.0). Mengembalikan:
     *  - kode   : kode asli BC ("001")
     *  - label  : label human-readable ("Perekaman Dokumen")
     *  - urgency: normal | urgent
     *  - stage  : proses | terminal | unknown
     *  - slug   : badge color key (HARUS match theme.ts STATUS_COLORS)
     *
     * Slug derivation:
     *  - terminal + normal + 800 → "hijau"   (Selesai Proses, sukses)
     *  - terminal + normal + 900 → "draft"   (Pembatalan)
     *  - terminal + urgent        → "failed" (Reject/Penolakan)
     *  - proses + urgent          → "notul"  (SPTNP, Penolakan Perbaikan)
     *  - proses + normal + 001    → "rekam"  (Perekaman Dokumen)
     *  - proses + normal + 230/240→ "aju"    (Siap Jalur/Penjaluran)
     *  - proses + normal (lain)   → "proses"
     *
     * @return array{kode:string, label:string, urgency:string, stage:string, slug:string}
     */
    protected function resolveKodeProses(string $kodeStatus): array
    {
        $kodeProses = (array) config('ceisa.kode_proses', []);
        $entry = $kodeProses[$kodeStatus] ?? null;

        $label = is_array($entry) ? ($entry['label'] ?? $kodeStatus) : $kodeStatus;
        $urgency = is_array($entry) ? ($entry['urgency'] ?? 'normal') : 'normal';
        $stage = is_array($entry) ? ($entry['stage'] ?? 'unknown') : 'unknown';

        // Derive slug untuk badge color (match theme.ts STATUS_COLORS keys).
        $slug = 'proses'; // default untuk proses normal
        if ($stage === 'terminal') {
            $slug = ($urgency === 'urgent')
                ? 'failed'
                : ($kodeStatus === '900' ? 'draft' : 'hijau');
        } elseif ($urgency === 'urgent') {
            $slug = 'notul';
        } elseif ($kodeStatus === '001') {
            $slug = 'rekam';
        } elseif (in_array($kodeStatus, ['230', '240'], true)) {
            $slug = 'aju';
        }

        return [
            'kode'    => $kodeStatus,
            'label'   => $label,
            'urgency' => $urgency,
            'stage'   => $stage,
            'slug'    => $slug,
        ];
    }

    /**
     * Ambil nomor pendaftaran (nomorDaftar) dari dataStatus BC.
     *
     * Struktur dataStatus (lihat OpenAPI v2 /status/{nomorAju}):
     * [{ nomorAju, kodeStatus, nomorDaftar, tanggalDaftar, waktuStatus, keterangan }]
     */
    protected function extractRegistrationNumber(array $dataStatus): ?string
    {
        if (empty($dataStatus)) {
            return null;
        }
        $latest = $dataStatus[0] ?? null;
        if (!is_array($latest)) {
            return null;
        }

        $nomor = $latest['nomorDaftar']
            ?? $latest['nomorPendaftaran']
            ?? $latest['noDaftar']
            ?? null;

        return $nomor !== null ? (string) $nomor : null;
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