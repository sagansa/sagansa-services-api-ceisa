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

        try {
            $response = $this->client->get($path, [], 'manifes');
            $result = $this->wrapProxy($response, 'pelabuhanDalam');

            if ($result['success'] && $this->isValidPelabuhanList($result['data'])) {
                return ['success' => true, 'data' => $this->normalizePelabuhan($result['data']), 'source' => 'bc'];
            }
        } catch (\Throwable $e) {
        }

        // Fallback: semua pelabuhan dalam negeri (kode kantor ignore di fallback).
        return ['success' => true, 'data' => self::PELABUHAN_DALAM_FALLBACK, 'source' => 'fallback'];
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

        try {
            $response = $this->client->get($path, [], 'manifes');
            $result = $this->wrapProxy($response, 'pelabuhanLuar');

            if ($result['success'] && $this->isValidPelabuhanList($result['data'])) {
                $data = $this->normalizePelabuhan($result['data']);
                // Filter ulang by kata bila BC return terlalu banyak.
                if ($kata) {
                    $data = array_values(array_filter($data, fn ($p) => stripos($p['nama'], $kata) !== false || stripos($p['kode'], $kata) !== false));
                }
                return ['success' => true, 'data' => $data, 'source' => 'bc'];
            }
        } catch (\Throwable $e) {
        }

        // Fallback: filter hardcoded list by kata.
        $all = self::PELABUHAN_LUAR_FALLBACK;
        $filtered = $kata !== ''
            ? array_values(array_filter($all, fn ($p) => stripos($p['nama'], $kata) !== false || stripos($p['kode'], $kata) !== false))
            : $all;

        return ['success' => true, 'data' => $filtered, 'source' => 'fallback'];
    }

    /** Cek apakah data BC adalah array of pelabuhan (bukan mock/error). */
    private function isValidPelabuhanList(mixed $data): bool
    {
        if (!is_array($data) || empty($data)) {
            return false;
        }
        // Mock response punya key "responseId"/"message" — bukan list pelabuhan.
        if (isset($data['responseId']) || isset($data['message'])) {
            return false;
        }
        // Valid: array of items (associative atau numeric-indexed).
        $first = reset($data);
        return is_array($first) || is_string($first) === false;
    }

    /** Normalisasi response BC (format bervariasi) ke [{kode, nama}]. */
    private function normalizePelabuhan(array $data): array
    {
        $normalized = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }
            $normalized[] = [
                'kode' => (string) ($item['kodePelabuhan'] ?? $item['kode'] ?? ''),
                'nama' => (string) ($item['namaPelabuhan'] ?? $item['nama'] ?? $item['uraian'] ?? ''),
            ];
        }
        return $normalized;
    }

    /**
     * GET /referensi/kantor
     *
     * Daftar kantor pabean Bea Cukai (kode kantor + nama).
     * Dipakai untuk dropdown "Kantor Pabean" di form PIB/PEB.
     *
     * Bila endpoint BC belum tersedia (404) atau gateway error, fallback
     * ke hardcoded list kantor pabean utama Indonesia. List ini cukup
     * lengkap untuk kebutuhan dropdown form (50+ kantor utama).
     *
     * @param string $kata Kata kunci pencarian opsional (nama/kode kantor).
     *                     Bila kosong, BC mengembalikan daftar lengkap.
     */
    public function kantor(string $kata = ''): array
    {
        $query = $kata !== '' ? ['kata' => $kata] : [];

        try {
            $response = $this->client->get($this->endpoint('ref_kantor'), $query, 'manifes');
            $result = $this->wrapProxy($response, 'kantor');

            // Bila BC return data valid (array of {kodeKantor, namaKantor}),
            // normalisasi ke format {kode, nama} dan pakai itu.
            if ($result['success'] && !empty($result['data'])) {
                $normalized = [];
                foreach ((array) $result['data'] as $item) {
                    if (!is_array($item)) {
                        continue; // skip string/non-array items
                    }
                    $normalized[] = [
                        'kode' => (string) ($item['kodeKantor'] ?? $item['kode'] ?? $item['kodeKantorPabean'] ?? ''),
                        'nama' => (string) ($item['namaKantor'] ?? $item['nama'] ?? $item['urKantor'] ?? $item['uraian'] ?? ''),
                    ];
                }
                if (!empty($normalized)) {
                    return [
                        'success' => true,
                        'data' => $normalized,
                        'source' => 'bc',
                    ];
                }
            }
        } catch (\Throwable $e) {
            // BC endpoint belum tersedia (404) atau gateway error.
            // Lanjut ke fallback hardcoded list.
        }

        // Fallback: hardcoded list kantor pabean, filter by kata kunci.
        $all = self::KANTOR_FALLBACK;
        $filtered = $kata !== ''
            ? array_values(array_filter($all, fn ($k) => stripos($k['nama'], $kata) !== false || stripos($k['kode'], $kata) !== false))
            : $all;

        return [
            'success' => true,
            'data' => $filtered,
            'source' => 'fallback',
        ];
    }

    /**
     * Hardcoded list kantor pabean utama (fallback bila BC 404).
     * Format: [kodeKantor, namaKantor].
     */
    private const KANTOR_FALLBACK = [
        ['kode' => '010000', 'nama' => 'Kanwil DJBC Jakarta'],
        ['kode' => '020000', 'nama' => 'Kanwil DJBC Jakarta II'],
        ['kode' => '030000', 'nama' => 'Kanwil DJBC Pekanbaru'],
        ['kode' => '040000', 'nama' => 'Kanwil DJBC Padang'],
        ['kode' => '050000', 'nama' => 'Kanwil DJBC Palembang'],
        ['kode' => '050100', 'nama' => 'KPU Teluk Bayur'],
        ['kode' => '050200', 'nama' => 'KPPBC Palembang'],
        ['kode' => '050300', 'nama' => 'KPPBC Jambi'],
        ['kode' => '050400', 'nama' => 'KPPBC Pangkal Pinang'],
        ['kode' => '060000', 'nama' => 'Kanwil DJBC Pontianak'],
        ['kode' => '070000', 'nama' => 'Kanwil DJBC Surabaya'],
        ['kode' => '070100', 'nama' => 'KPU Tanjung Perak'],
        ['kode' => '070200', 'nama' => 'KPPBC Pasuruan'],
        ['kode' => '070300', 'nama' => 'KPPBC Juwana'],
        ['kode' => '070500', 'nama' => 'KPPBC Tuban'],
        ['kode' => '080000', 'nama' => 'Kanwil DJBC Semarang'],
        ['kode' => '080100', 'nama' => 'KPU Semarang'],
        ['kode' => '080200', 'nama' => 'KPPBC Tegal'],
        ['kode' => '080300', 'nama' => 'KPPBC Cilacap'],
        ['kode' => '090000', 'nama' => 'Kanwil DJBC Bali & Nusa Tenggara'],
        ['kode' => '090100', 'nama' => 'KPU Denpasar'],
        ['kode' => '090300', 'nama' => 'KPPBC Mataram'],
        ['kode' => '090400', 'nama' => 'KPPBC Kupang'],
        ['kode' => '100000', 'nama' => 'Kanwil DJBC Makassar'],
        ['kode' => '100100', 'nama' => 'KPU Makassar'],
        ['kode' => '100300', 'nama' => 'KPPBC Palu'],
        ['kode' => '100400', 'nama' => 'KPPBC Kendari'],
        ['kode' => '110000', 'nama' => 'Kanwil DJBC Manado'],
        ['kode' => '110100', 'nama' => 'KPU Manado'],
        ['kode' => '110200', 'nama' => 'KPPBC Gorontalo'],
        ['kode' => '120000', 'nama' => 'Kanwil DJBC Ambon'],
        ['kode' => '120100', 'nama' => 'KPU Ambon'],
        ['kode' => '130000', 'nama' => 'Kanwil DJBC Sorong'],
        ['kode' => '140000', 'nama' => 'Kanwil DJBC Jayapura'],
        ['kode' => '150000', 'nama' => 'Kanwil DJBC Medan'],
        ['kode' => '150100', 'nama' => 'KPU Belawan'],
        ['kode' => '150200', 'nama' => 'KPPBC Medan'],
        ['kode' => '150300', 'nama' => 'KPPBC Lhokseumawe'],
        ['kode' => '160000', 'nama' => 'Kanwil DJBC Banda Aceh'],
        ['kode' => '160100', 'nama' => 'KPU Sabang'],
        ['kode' => '170000', 'nama' => 'Kanwil DJBC Padang'],
        ['kode' => '180000', 'nama' => 'Kanwil DJBC Bengkulu'],
        ['kode' => '190000', 'nama' => 'Kanwil DJBC Lampung'],
        ['kode' => '190100', 'nama' => 'KPPBC Panjang'],
        ['kode' => '200000', 'nama' => 'Kanwil DJBC Banjarmasin'],
        ['kode' => '200100', 'nama' => 'KPU Banjarmasin'],
        ['kode' => '210000', 'nama' => 'Kanwil DJBC Samarinda'],
        ['kode' => '210100', 'nama' => 'KPU Samarinda'],
        ['kode' => '210200', 'nama' => 'KPPBC Tarakan'],
        ['kode' => '220000', 'nama' => 'Kanwil DJBC Manado'],
        ['kode' => '060100', 'nama' => 'KPU Pontianak'],
        ['kode' => '201203', 'nama' => 'KPPBC TMP C Padang'],
        ['kode' => '040100', 'nama' => 'KPU Padang'],
        ['kode' => '010200', 'nama' => 'KPU Tanjung Priok'],
        ['kode' => '010300', 'nama' => 'KPPBC Marunda'],
        ['kode' => '010400', 'nama' => 'KPPBC Cilincing'],
        ['kode' => '010500', 'nama' => 'KPPBC Bandar Soekarno-Hatta'],
        ['kode' => '020100', 'nama' => 'KPU Cirebon'],
        ['kode' => '020200', 'nama' => 'KPPBC Bogor'],
        ['kode' => '020300', 'nama' => 'KPPBC Tangerang'],
        ['kode' => '020400', 'nama' => 'KPPBC Bekasi'],
    ];

    /**
     * Hardcoded list pelabuhan luar negeri utama (fallback bila BC mock/404).
     * Format: [kodePelabuhan, namaPelabuhan].
     */
    private const PELABUHAN_LUAR_FALLBACK = [
        ['kode' => 'SGSIN', 'nama' => 'Singapore'],
        ['kode' => 'MYPKG', 'nama' => 'Port Klang, Malaysia'],
        ['kode' => 'MYPPK', 'nama' => 'Penang, Malaysia'],
        ['kode' => 'CN sha', 'nama' => 'Shanghai, China'],
        ['kode' => 'CNSZX', 'nama' => 'Shenzhen, China'],
        ['kode' => 'CNNTG', 'nama' => 'Ningbo, China'],
        ['kode' => 'CNGZG', 'nama' => 'Guangzhou, China'],
        ['kode' => 'HKHKG', 'nama' => 'Hong Kong'],
        ['kode' => 'TWTPE', 'nama' => 'Taipei, Taiwan'],
        ['kode' => 'JPTYO', 'nama' => 'Tokyo, Japan'],
        ['kode' => 'JPYOK', 'nama' => 'Yokohama, Japan'],
        ['kode' => 'JPOSA', 'nama' => 'Osaka, Japan'],
        ['kode' => 'JPKBE', 'nama' => 'Kobe, Japan'],
        ['kode' => 'KRPUS', 'nama' => 'Busan, South Korea'],
        ['kode' => 'KRINC', 'nama' => 'Incheon, South Korea'],
        ['kode' => 'THBKK', 'nama' => 'Bangkok, Thailand'],
        ['kode' => 'VNSGN', 'nama' => 'Ho Chi Minh, Vietnam'],
        ['kode' => 'VNHAN', 'nama' => 'Haiphong, Vietnam'],
        ['kode' => 'PHMNL', 'nama' => 'Manila, Philippines'],
        ['kode' => 'IDSUB', 'nama' => 'Subic Bay, Philippines'],
        ['kode' => 'INMUN', 'nama' => 'Mumbai, India'],
        ['kode' => 'INMAA', 'nama' => 'Chennai, India'],
        ['kode' => 'AUSYD', 'nama' => 'Sydney, Australia'],
        ['kode' => 'AUMEL', 'nama' => 'Melbourne, Australia'],
        ['kode' => 'USLAX', 'nama' => 'Los Angeles, USA'],
        ['kode' => 'USOAK', 'nama' => 'Oakland, USA'],
        ['kode' => 'USNYC', 'nama' => 'New York, USA'],
        ['kode' => 'USSEA', 'nama' => 'Seattle, USA'],
        ['kode' => 'NLRTM', 'nama' => 'Rotterdam, Netherlands'],
        ['kode' => 'DEHAM', 'nama' => 'Hamburg, Germany'],
        ['kode' => 'GBFXT', 'nama' => 'Felixstowe, UK'],
        ['kode' => 'BEANR', 'nama' => 'Antwerp, Belgium'],
        ['kode' => 'FRMRS', 'nama' => 'Marseille, France'],
        ['kode' => 'ITGOA', 'nama' => 'Genoa, Italy'],
        ['kode' => 'ESBCN', 'nama' => 'Barcelona, Spain'],
        ['kode' => 'TRIST', 'nama' => 'Istanbul, Turkey'],
        ['kode' => 'AEDXB', 'nama' => 'Dubai, UAE'],
        ['kode' => 'SAJED', 'nama' => 'Jeddah, Saudi Arabia'],
        ['kode' => 'EGALY', 'nama' => 'Alexandria, Egypt'],
        ['kode' => 'ZADUR', 'nama' => 'Durban, South Africa'],
    ];

    /**
     * Hardcoded list pelabuhan dalam negeri utama (fallback bila BC mock/404).
     * Format: [kodePelabuhan, namaPelabuhan].
     */
    private const PELABUHAN_DALAM_FALLBACK = [
        ['kode' => 'IDJKT', 'nama' => 'Tanjung Priok, Jakarta'],
        ['kode' => 'IDTPP', 'nama' => 'Tanjung Perak, Surabaya'],
        ['kode' => 'IDBLW', 'nama' => 'Belawan, Medan'],
        ['kode' => 'IDPNJ', 'nama' => 'Panjang, Lampung'],
        ['kode' => 'IDPLG', 'nama' => 'Palembang'],
        ['kode' => 'IDJMB', 'nama' => 'Jambi'],
        ['kode' => 'IDMKS', 'nama' => 'Makassar'],
        ['kode' => 'IDSRG', 'nama' => 'Semarang'],
        ['kode' => 'IDBTM', 'nama' => 'Batam'],
        ['kode' => 'IDBTH', 'nama' => 'Bengkulu'],
        ['kode' => 'IDBXM', 'nama' => 'Balikpapan'],
        ['kode' => 'IDBDJ', 'nama' => 'Banjarmasin'],
        ['kode' => 'IDSRI', 'nama' => 'Samarinda'],
        ['kode' => 'IDTRK', 'nama' => 'Tarakan'],
        ['kode' => 'IDTLB', 'nama' => 'Teluk Bayur, Padang'],
        ['kode' => 'IDPHE', 'nama' => 'Pontianak'],
        ['kode' => 'IDDPS', 'nama' => 'Denpasar, Bali'],
        ['kode' => 'IDLOP', 'nama' => 'Lombok'],
        ['kode' => 'IDKOE', 'nama' => 'Kupang'],
        ['kode' => 'IDMDC', 'nama' => 'Manado'],
        ['kode' => 'IDGTO', 'nama' => 'Gorontalo'],
        ['kode' => 'IDPLU', 'nama' => 'Palu'],
        ['kode' => 'IDKDI', 'nama' => 'Kendari'],
        ['kode' => 'IDAMQ', 'nama' => 'Ambon'],
        ['kode' => 'IDSOQ', 'nama' => 'Sorong'],
        ['kode' => 'IDDJJ', 'nama' => 'Jayapura'],
        ['kode' => 'IDTNT', 'nama' => 'Ternate'],
        ['kode' => 'IDSBI', 'nama' => 'Sabang'],
        ['kode' => 'IDGTY', 'nama' => 'Cilacap'],
        ['kode' => 'IDPEK', 'nama' => 'Pekanbaru'],
        ['kode' => 'IDTGL', 'nama' => 'Tegal'],
        ['kode' => 'IDPSR', 'nama' => 'Pasuruan'],
        ['kode' => 'IDJWN', 'nama' => 'Juwana'],
        ['kode' => 'IDTBN', 'nama' => 'Tuban'],
    ];

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
