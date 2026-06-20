<?php

namespace App\Services;

use App\Models\ManifesDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orkestrasi pemanggilan endpoint CEISA 4.0 OpenAPI v2 (openapi path unified).
 *
 * BREAKING v2: Sejak OpenAPI v2, base path sudah unified (/v2/openapi) dan
 * struktur endpoint berubah. Mapping utama:
 *
 *   v1 GET /manifes/nvocc/status/{nomorAju}
 *     → v2 GET /status/{nomorAju}              (status unified dokumen pabean)
 *
 *   v1 GET /manifes/inward?kodeKantor=&nomorBl=&tanggalBl=
 *     → v2 GET /manifes-bc11?kodeKantor=&noHostBl=&tglHostBl=&nama=
 *
 *   v1 GET /manifes/respon/pdf?idRespon=
 *     → v2 GET /respon/pdf?kodeRespon=&nomorAju=
 *
 * Catatan: endpoint write manifes (draft/kirim/rekon/bc11/report-manifes) yang
 * spesifik NVOCC tidak ada di spec v2 (OpenAPI v2 hanya expose Inward Manifes
 * BC 1.1 sebagai read-only). Method lama tetap dipertahankan agar tidak break
 * caller eksisting, tetapi akan resolve ke path lama (yang mungkin 404 di v2).
 *
 * Sumber: doc/json/Export_openapi_v2_*.json (OpenAPI 3.0.1, version 2.0).
 *
 * Semua request otomatis menyertakan auth (beacukai-api-key + Bearer) via CeisaClient.
 */
class ManifesService
{
    public function __construct(protected CeisaClient $client)
    {
    }

    /**
     * GET history status & respon dokumen by nomorAju (v2 unified endpoint).
     *
     * Endpoint v2: GET /status/{nomorAju}
     *
     * Response v2 (lihat contoh example OpenAPI v2):
     * {
     *   "dataStatus": [{
     *     "nomorAju", "kodeStatus", "nomorDaftar", "tanggalDaftar",
     *     "waktuStatus", "keterangan"
     *   }],
     *   "dataRespon": [{
     *     "nomorAju", "kodeRespon", "nomorDaftar", "tanggalDaftar",
     *     "nomorRespon", "tanggalRespon", "waktuRespon", "waktuStatus",
     *     "keterangan", "pesan":[{uraian1, uraian2}], "Pdf":"base64"
     *   }]
     * }
     *
     * @param string $nomorAju Nomor Aju Dokumen (maksimal 26 karakter, status H@H).
     * @return array{
     *     success: bool,
     *     status_code: int,
     *     nomor_aju: string,
     *     history: array,
     *     latest_status: ?string,
     *     raw: array,
     *     error: ?string
     * }
     */
    public function getStatus(string $nomorAju): array
    {
        // v2 unified status endpoint — backward-compat: pakai key 'manifes_status'
        // yang sekarang di-alias ke '/status/{nomorAju}' di config.
        $path = $this->endpoint('manifes_status', ['nomorAju' => $nomorAju]);

        try {
            $response = $this->client->get($path, [], 'manifes');
            $code = $response['status'];
            $body = $response['body'];

            // v2 response: gabungan dataStatus + dataRespon
            $dataStatus = $body['dataStatus'] ?? ($body['data']['dataStatus'] ?? []);
            $history = is_array($dataStatus) ? $dataStatus : [];
            $latestStatus = $this->extractLatestStatus($history);

            // Persist ke DB untuk audit + cache tampilan mobile.
            $this->persistStatusResponse($nomorAju, $code, $body, $latestStatus);

            $success = $code >= 200 && $code < 300;

            return [
                'success'       => $success,
                'status_code'   => $code,
                'nomor_aju'     => $nomorAju,
                'history'       => $history,
                'data_respon'   => $body['dataRespon'] ?? [],
                'latest_status' => $latestStatus,
                'raw'           => $body,
                'error'         => $success ? null : ($body['message'] ?? "HTTP {$code}"),
            ];
        } catch (\Throwable $e) {
            Log::error('ManifesService getStatus failed', [
                'nomor_aju' => $nomorAju,
                'error'     => $e->getMessage(),
            ]);

            return [
                'success'       => false,
                'status_code'   => 0,
                'nomor_aju'     => $nomorAju,
                'history'       => [],
                'data_respon'   => [],
                'latest_status' => null,
                'raw'           => [],
                'error'         => $e->getMessage(),
            ];
        }
    }

    /**
     * GET Inward Manifes BC 1.1 (v2 endpoint).
     *
     * Endpoint v2: GET /manifes-bc11?nama=&tglHostBl=&kodeKantor=&noHostBl=
     *
     * Catatan parameter v2 (berbeda dari v1):
     *   - nama        : Nama Importir (wajib)
     *   - tglHostBl   : Tanggal Host B/L format DD-MM-YYYY (wajib)
     *   - kodeKantor  : Kode Kantor (wajib)
     *   - noHostBl    : Nomor Host B/L (wajib)
     */
    public function inward(string $kodeKantor, string $nomorBl, string $tanggalBl, ?string $nama = null): array
    {
        // v2 manifes-bc11 endpoint — backward-compat: pakai key 'manifes_inward'
        // yang sekarang di-alias ke '/manifes-bc11' di config.
        $path = $this->endpoint('manifes_inward');

        return $this->client->get($path, [
            'kodeKantor' => $kodeKantor,
            'noHostBl'   => $nomorBl,
            'tglHostBl'  => $tanggalBl,
            'nama'       => $nama ?? '',
        ], 'manifes');
    }

    /**
     * GET /respon/pdf?kodeRespon=&nomorAju= — PDF Respon (v2 unified endpoint).
     *
     * Parameter v2: kodeRespon + nomorAju (menggantikan idRespon v1).
     */
    public function responPdf(string $kodeRespon, string $nomorAju): array
    {
        $path = $this->endpoint('manifes_respon_pdf');

        return $this->client->get($path, [
            'kodeRespon' => $kodeRespon,
            'nomorAju'   => $nomorAju,
        ], 'manifes');
    }

    // -----------------------------------------------------------------------
    // LEGACY write endpoints (v1 NVOCC). Catatan: spec v2 tidak expose ini;
    // dipertahankan untuk backward-compat tapi mungkin 404 di gateway v2.
    // Caller baru disarankan pakai DocumentService (POST /document) untuk
    // submit dokumen pabean, atau StatusService untuk cek status.
    // -----------------------------------------------------------------------

    /**
     * [LEGACY v1] POST /manifes/nvocc/draft — drafting manifes (NVOCC).
     *
     * @deprecated v2 OpenAPI tidak expose endpoint draft NVOCC. Gunakan
     *             POST /document?isFinal=false untuk draft dokumen pabean baru.
     */
    public function draft(array $payload): array
    {
        $path = (string) config('ceisa.endpoints.manifes_draft', '/manifes/nvocc/draft');
        $path = $this->substituteParams($path);
        $response = $this->client->post($path, $payload, 'manifes');

        $nomorAju = $payload['nomorAju'] ?? null;
        $idNvoccHeader = $response['body']['idNvoccHeader']
            ?? $response['body']['data']['idNvoccHeader']
            ?? null;

        if ($nomorAju) {
            ManifesDocument::firstOrCreateByNomorAju($nomorAju, [
                'kode_kantor'            => $payload['kodeKantor'] ?? null,
                'jenis_manifes'          => $payload['jenisManifes'] ?? null,
                'nomor_voyage'           => $payload['nomorVoyage'] ?? null,
                'nama_sarana_pengangkut' => $payload['namaSaranaPengangkut'] ?? null,
                'imo_number'             => $payload['imoNumber'] ?? null,
                'mode_pengangkut'        => $payload['modePengangkut'] ?? null,
                'kode_negara'            => $payload['kodeNegara'] ?? null,
                'status'                 => 'DRAFT',
                'id_nvocc_header'        => $idNvoccHeader,
                'drafted_at'             => now(),
            ]);
        }

        return $response;
    }

    /**
     * [LEGACY v1] DELETE /manifes/nvocc/draft/{nomorAju} — hapus draft.
     *
     * @deprecated v2 OpenAPI tidak expose endpoint ini.
     */
    public function deleteDraft(string $nomorAju): array
    {
        $path = (string) config('ceisa.endpoints.manifes_draft_delete', '/manifes/nvocc/draft/{nomorAju}');
        $path = $this->substituteParams($path, ['nomorAju' => $nomorAju]);

        return $this->client->delete($path, [], 'manifes');
    }

    /**
     * [LEGACY v1] POST /manifes/nvocc/cek-lengkap — validasi → status READY.
     *
     * @deprecated v2 OpenAPI tidak expose endpoint ini.
     */
    public function cekLengkap(string $nomorAju): array
    {
        $path = (string) config('ceisa.endpoints.manifes_cek_lengkap', '/manifes/nvocc/cek-lengkap');

        return $this->client->post($path, ['nomorAju' => $nomorAju], 'manifes');
    }

    /**
     * [LEGACY v1] POST /manifes/nvocc/kirim — kirim draft READY.
     *
     * @deprecated v2: gunakan POST /document?isFinal=true via DocumentService.
     */
    public function kirim(array $payload): array
    {
        $path = (string) config('ceisa.endpoints.manifes_kirim', '/manifes/nvocc/kirim');
        $path = $this->substituteParams($path);
        $response = $this->client->post($path, $payload, 'manifes');

        $nomorAju = $payload['nomorAju'] ?? null;
        if ($nomorAju && ($response['status'] >= 200 && $response['status'] < 300)) {
            ManifesDocument::where('nomor_aju', $nomorAju)->update([
                'status'        => 'PROSES',
                'submitted_at'  => now(),
            ]);
        }

        return $response;
    }

    /**
     * [LEGACY v1] POST /manifes/nvocc/rekon — rekonsiliasi pecah pos.
     *
     * @deprecated v2 OpenAPI tidak expose endpoint ini.
     */
    public function rekon(array $payload): array
    {
        $path = (string) config('ceisa.endpoints.manifes_rekon', '/manifes/nvocc/rekon');
        $path = $this->substituteParams($path);
        $response = $this->client->post($path, $payload, 'manifes');

        $nomorAju = $payload['nomorAju'] ?? null;
        if ($nomorAju && ($response['status'] >= 200 && $response['status'] < 300)) {
            ManifesDocument::where('nomor_aju', $nomorAju)->update([
                'rekon_at' => now(),
            ]);
        }

        return $response;
    }

    /**
     * [LEGACY v1] GET /manifes/nvocc/bc11?nomorAju=...
     *
     * @deprecated v2: gunakan GET /manifes-bc11 (inward) dengan parameter
     *             nama/tglHostBl/kodeKantor/noHostBl.
     */
    public function bc11(string $nomorAju): array
    {
        $path = (string) config('ceisa.endpoints.manifes_bc11_legacy', '/manifes/nvocc/bc11');

        return $this->client->get($path, ['nomorAju' => $nomorAju], 'manifes');
    }

    /**
     * [LEGACY v1] GET /manifes/nvocc/report-manifes/{idNvoccHeader}.
     *
     * @deprecated v2 OpenAPI tidak expose endpoint ini.
     */
    public function reportPdf(string $idNvoccHeader): array
    {
        $path = (string) config('ceisa.endpoints.manifes_report', '/manifes/nvocc/report-manifes/{idNvoccHeader}');
        $path = $this->substituteParams($path, ['idNvoccHeader' => $idNvoccHeader]);

        return $this->client->get($path, [], 'manifes');
    }

    /**
     * [LEGACY v1] GET /manifes/outward — pos manifes outward.
     *
     * Catatan: v2 OpenAPI tidak expose endpoint outward terpisah. Disarankan
     * menggunakan /manifes-bc11 untuk inward; outward belum tersedia di v2.
     */
    public function outward(string $kodeKantor, string $nomorBl, string $tanggalBl): array
    {
        $path = (string) config('ceisa.endpoints.manifes_outward', '/manifes/outward');

        return $this->client->get($path, [
            'kodeKantor' => $kodeKantor,
            'nomorBl'    => $nomorBl,
            'tanggalBl'  => $tanggalBl,
        ], 'manifes');
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Resolve endpoint config key dan substitute placeholder {nomorAju} / {id}.
     */
    protected function endpoint(string $key, array $params = []): string
    {
        $path = (string) config("ceisa.endpoints.{$key}", "/{$key}");

        return $this->substituteParams($path, $params);
    }

    /**
     * Substitute placeholder {key} dalam path.
     */
    protected function substituteParams(string $path, array $params = []): string
    {
        foreach ($params as $k => $v) {
            $path = str_replace('{' . $k . '}', (string) $v, $path);
        }

        return $path;
    }

    /**
     * Ambil status terbaru dari list dataStatus response v2.
     *
     * Field v2: kodeStatus (lihat example OpenAPI v2 /status/{nomorAju}).
     * Backward-compat: cek juga field lama (statusRespon, status).
     */
    protected function extractLatestStatus(array $history): ?string
    {
        if (empty($history)) {
            return null;
        }
        $latest = $history[0] ?? null;
        if (!is_array($latest)) {
            return null;
        }

        return $latest['kodeStatus']
            ?? $latest['statusRespon']
            ?? $latest['status']
            ?? null;
    }

    /**
     * Simpan response status ke DB (audit + cache tampilan).
     */
    protected function persistStatusResponse(string $nomorAju, int $code, array $body, ?string $latestStatus): void
    {
        try {
            $doc = ManifesDocument::firstOrCreateByNomorAju($nomorAju);
            $doc->update([
                'status'                => $latestStatus ?? $doc->status,
                'last_status_check_at'  => now(),
                'last_status_response'  => $body,
            ]);
        } catch (\Throwable $e) {
            // Jangan gagalkan request hanya karena gagal simpan cache DB.
            Log::warning('ManifesService: gagal persist status ke DB', [
                'nomor_aju' => $nomorAju,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}