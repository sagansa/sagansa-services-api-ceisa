<?php

namespace App\Services;

/**
 * Referensi & Kurs Service — CEISA 4.0 OpenAPI v2.
 *
 * Membungkus endpoint referensi & kurs di gateway BC:
 *
 *   GET /referensi/pelabuhan-dalam-negeri/{kodeKantor}  → pelabuhan dalam negeri
 *   GET /referensi/pelabuhan-luar-negeri/{kata}         → pelabuhan luar negeri (search)
 *   GET /referensi/tps-gudang/{kodeKantor}              → kode gudang TPS
 *   GET /kurs/{kodeValuta}                              → Kurs NDPBM (valuta)
 *   GET /generate-pungutan/20/{nomorAju}                → Generate pungutan BC 2.0
 *
 * Endpoint ini adalah master data BC — sering dipakai operator saat:
 *  - Mengisi form PIB (cari pelabuhan, TPS gudang)
 *  - Cek kurs NDPBM terbaru sebelum deklarasi nilai CIF
 *  - Hitung pungutan setelah PIB terdaftar
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: referensi-controller.
 */
class ReferensiService extends CeisaBaseService
{
    /**
     * GET /referensi/pelabuhan-dalam-negeri/{kodeKantor}
     *
     * @param string $kodeKantor Kode kantor pabean (mis. "050100").
     */
    public function pelabuhanDalam(string $kodeKantor): array
    {
        $path = $this->endpoint('ref_pelabuhan_dalam', ['kodeKantor' => $kodeKantor]);
        $response = $this->client->get($path, [], 'manifes');

        return $this->wrapProxy($response, 'pelabuhanDalam');
    }

    /**
     * GET /referensi/pelabuhan-luar-negeri/{kata}
     *
     * Endpoint search — kata = bagian nama pelabuhan luar negeri.
     *
     * @param string $kata Kata kunci pencarian (mis. "singa" → Singapore).
     */
    public function pelabuhanLuar(string $kata): array
    {
        $path = $this->endpoint('ref_pelabuhan_luar', ['kata' => $kata]);
        $response = $this->client->get($path, [], 'manifes');

        return $this->wrapProxy($response, 'pelabuhanLuar');
    }

    /**
     * GET /referensi/kantor
     *
     * Daftar kantor pabean Bea Cukai (kode kantor + nama).
     * Dipakai untuk dropdown "Kantor Pabean" di form PIB/PEB.
     *
     * @param string $kata Kata kunci pencarian opsional (nama/kode kantor).
     *                     Bila kosong, BC mengembalikan daftar lengkap.
     */
    public function kantor(string $kata = ''): array
    {
        $query = $kata !== '' ? ['kata' => $kata] : [];
        $response = $this->client->get($this->endpoint('ref_kantor'), $query, 'manifes');

        return $this->wrapProxy($response, 'kantor');
    }

    /**
     * GET /referensi/tps-gudang/{kodeKantor}
     *
     * Daftar gudang TPS untuk kantor pabean tertentu.
     */
    public function tpsGudang(string $kodeKantor): array
    {
        $path = $this->endpoint('ref_tps_gudang', ['kodeKantor' => $kodeKantor]);
        $response = $this->client->get($path, [], 'manifes');

        return $this->wrapProxy($response, 'tpsGudang');
    }

    /**
     * GET /kurs/{kodeValuta}
     *
     * Kurs NDPBM (Nilai Dasar Penghitungan Bea Masuk) untuk kode valuta.
     *
     * @param string $kodeValuta Kode valuta (mis. "USD", "EUR", "JPY").
     */
    public function kurs(string $kodeValuta): array
    {
        $path = $this->endpoint('kurs', ['kodeValuta' => $kodeValuta]);
        $response = $this->client->get($path, [], 'manifes');

        return $this->wrapProxy($response, 'kurs');
    }

    /**
     * GET /generate-pungutan/20/{nomorAju}
     *
     * Generate pungutan BC 2.0 (PIB) untuk nomorAju tertentu.
     * Biasanya dipanggil setelah PIB terdaftar untuk lihat rincian bea masuk + PPN/PPH.
     *
     * @param string $nomorAju Nomor Aju PIB (26 karakter).
     */
    public function generatePungutan(string $nomorAju): array
    {
        $path = $this->endpoint('generate_pungutan_bc20', ['nomorAju' => $nomorAju]);
        $response = $this->client->get($path, [], 'manifes');

        return $this->wrapProxy($response, 'generatePungutan');
    }
}
