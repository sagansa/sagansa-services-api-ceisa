<?php

namespace App\Services;

/**
 * Cukai Service — CEISA 4.0 Host to Host Cukai (openapi-cukai v1.0).
 *
 * Monitoring & pelaporan mesin produksi Barang Kena Cukai (BKC):
 * rokok (hasil tembakau), MMEA (minuman mengandung etanol / alkohol),
 * dan mirasantisa.
 *
 * BERBEDA dari openapi v2 — base path terpisah /v1/openapi-cukai
 * (di-resolve via service_paths.cukai). Semua pemanggilan CeisaClient
 * di service ini mengirim argumen service = 'cukai'.
 *
 * Dikelompokkan menjadi 4 kategori (13 endpoint):
 *
 * 1. GPS (3 endpoint):
 *    GET  /h2h/gps          → list GPS dengan filter (page, limit, sort, dll)
 *    GET  /h2h/gps/{id}     → detail GPS by ID
 *    POST /h2h/gps          → tambah lokasi GPS baru
 *
 * 2. Mesin (4 endpoint):
 *    GET    /h2h/mesin      → list mesin dengan filter
 *    POST   /h2h/mesin      → tambah mesin (with image upload)
 *    PUT    /h2h/mesin/{id} → update mesin (with image upload)
 *    DELETE /h2h/mesin/{id} → hapus mesin (soft delete)
 *
 * 3. Produksi (5 endpoint):
 *    GET  /h2h/mesin/produksi              → list laporan produksi
 *    POST /h2h/mesin/produksi/batang       → tambah produksi per batang (single)
 *    POST /h2h/mesin/produksi/batang/batch → tambah produksi per batang (batch)
 *    POST /h2h/mesin/produksi/kemasan      → tambah produksi per kemasan (single)
 *    POST /h2h/mesin/produksi/kemasan/batch→ tambah produksi per kemasan (batch)
 *
 * 4. Referensi (4 endpoint):
 *    GET /h2h/referensi/jenis-mesin        → list jenis mesin
 *    GET /h2h/referensi/tipe-mesin         → list tipe mesin
 *    GET /h2h/referensi/kondisi            → list kondisi mesin
 *    GET /h2h/referensi/status-kepemilikan → list status kepemilikan
 *
 * Sumber: portal BC → openapi-cukai v1.0 (openapicukai_openapi.json).
 */
class CukaiService extends CeisaBaseService
{
    /**
     * Service key untuk resolve base path /v1/openapi-cukai.
     * Semua method di service ini mengirimkan nilai ini ke CeisaClient.
     */
    protected const SERVICE = 'cukai';

    // ===== 1. GPS =====

    /**
     * GET /h2h/gps — Daftar GPS dengan filter & pagination.
     *
     * @param array $filters Query params opsional:
     *   page, limit, sortField, sortOrder,
     *   idMesinHeader, serialNumberDevice, posisi
     */
    public function listGps(array $filters = []): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cukai_gps_list'), $filters, self::SERVICE),
            'listGps',
        );
    }

    /** GET /h2h/gps/{id} — Detail GPS by ID. */
    public function getGpsById(string $id): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cukai_gps_by_id', ['id' => $id]), [], self::SERVICE),
            'getGpsById',
        );
    }

    /** POST /h2h/gps — Tambah lokasi GPS baru. */
    public function createGps(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cukai_gps_list'), $payload, self::SERVICE),
            'createGps',
        );
    }

    // ===== 2. Mesin =====

    /**
     * GET /h2h/mesin — Daftar mesin dengan filter & pagination.
     *
     * @param array $filters Query params opsional:
     *   page, limit, sortField, sortOrder, merekMesin, keterangan,
     *   nomorIdentifikasi, serialNumber, lokasi,
     *   kodeTipeMesin, kodeJenisMesin, kodeKondisi,
     *   kodeStatusKepemilikkan, kodeKantor
     */
    public function listMesin(array $filters = []): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cukai_mesin_list'), $filters, self::SERVICE),
            'listMesin',
        );
    }

    /**
     * POST /h2h/mesin — Tambah mesin baru (with image upload).
     *
     * Spec BC menyebut "with image upload". Bila request mengandung file
     * foto (mis. base64 atau multipart), gunakan multipart form. Bila tidak,
     * kirim JSON body biasa.
     *
     * @param array $payload Data mesin + opsional 'files' untuk foto.
     */
    public function createMesin(array $payload): array
    {
        $files = $payload['files'] ?? [];
        unset($payload['files']);

        if (!empty($files)) {
            return $this->wrapProxy(
                $this->client->postMultipart($this->endpoint('cukai_mesin_list'), $files, $payload, self::SERVICE),
                'createMesin',
            );
        }

        return $this->wrapProxy(
            $this->client->post($this->endpoint('cukai_mesin_list'), $payload, self::SERVICE),
            'createMesin',
        );
    }

    /**
     * PUT /h2h/mesin/{id} — Update mesin (with image upload).
     *
     * @param array $payload Data mesin + opsional 'files' + 'removePhotos'.
     */
    public function updateMesin(string $id, array $payload): array
    {
        $query = [];
        if (isset($payload['removePhotos'])) {
            $query['removePhotos'] = $payload['removePhotos'];
            unset($payload['removePhotos']);
        }
        $files = $payload['files'] ?? [];
        unset($payload['files']);

        // PUT multipart tidak didukung wrapper saat ini; bila ada file,
        // kita kirim via post() dengan body JSON (BC umumnya menerima keduanya
        // atau punya endpoint foto terpisah). File upload untuk update mesin
        // dapat di-enhance kemudian via endpoint foto khusus bila BC menolak.
        return $this->wrapProxy(
            $this->client->put($this->endpoint('cukai_mesin_by_id', ['id' => $id]), $payload, self::SERVICE, $query),
            'updateMesin',
        );
    }

    /** DELETE /h2h/mesin/{id} — Hapus mesin (soft delete). */
    public function deleteMesin(string $id): array
    {
        return $this->wrapProxy(
            $this->client->delete($this->endpoint('cukai_mesin_by_id', ['id' => $id]), [], self::SERVICE),
            'deleteMesin',
        );
    }

    // ===== 3. Produksi =====

    /**
     * GET /h2h/mesin/produksi — Daftar laporan produksi dengan filter.
     *
     * @param array $filters page, limit, sortField, sortOrder,
     *   idMerek, idProduksi, keterangan
     */
    public function listProduksi(array $filters = []): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cukai_produksi_list'), $filters, self::SERVICE),
            'listProduksi',
        );
    }

    /** POST /h2h/mesin/produksi/batang — Tambah laporan produksi per batang (single). */
    public function createProduksiBatang(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cukai_produksi_batang'), $payload, self::SERVICE),
            'createProduksiBatang',
        );
    }

    /** POST /h2h/mesin/produksi/batang/batch — Tambah laporan produksi per batang (batch). */
    public function createProduksiBatangBatch(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cukai_produksi_batang_batch'), $payload, self::SERVICE),
            'createProduksiBatangBatch',
        );
    }

    /** POST /h2h/mesin/produksi/kemasan — Tambah laporan produksi per kemasan (single). */
    public function createProduksiKemasan(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cukai_produksi_kemasan'), $payload, self::SERVICE),
            'createProduksiKemasan',
        );
    }

    /** POST /h2h/mesin/produksi/kemasan/batch — Tambah laporan produksi per kemasan (batch). */
    public function createProduksiKemasanBatch(array $payload): array
    {
        return $this->wrapProxy(
            $this->client->post($this->endpoint('cukai_produksi_kemasan_batch'), $payload, self::SERVICE),
            'createProduksiKemasanBatch',
        );
    }

    // ===== 4. Referensi =====

    /** GET /h2h/referensi/jenis-mesin — List jenis mesin. */
    public function refJenisMesin(): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cukai_ref_jenis_mesin'), [], self::SERVICE),
            'refJenisMesin',
        );
    }

    /** GET /h2h/referensi/tipe-mesin — List tipe mesin. */
    public function refTipeMesin(): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cukai_ref_tipe_mesin'), [], self::SERVICE),
            'refTipeMesin',
        );
    }

    /** GET /h2h/referensi/kondisi — List kondisi mesin. */
    public function refKondisi(): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cukai_ref_kondisi'), [], self::SERVICE),
            'refKondisi',
        );
    }

    /** GET /h2h/referensi/status-kepemilikan — List status kepemilikan. */
    public function refStatusKepemilikan(): array
    {
        return $this->wrapProxy(
            $this->client->get($this->endpoint('cukai_ref_status_kepemilikan'), [], self::SERVICE),
            'refStatusKepemilikan',
        );
    }
}
