<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OSS/RBA Service — lookup data perusahaan berdasarkan NIB.
 *
 * OSS (Online Single Submission for Investment) menyediakan API publik
 * untuk validasi NIB (Nomor Induk Berusaha) dan return data perusahaan:
 * nama, alamat, npwp, jenis badan usaha, dll.
 *
 * Endpoint publik OSS-RBA:
 *   GET https://pbcb.oss.go.id/oss/api/v1/nib/{nib}
 *
 * Bila API OSS tidak tersedia/tidak terkonfigurasi (sandbox), fallback
 * ke data hardcoded demo atau data dari BC user profile.
 */
class OssService
{
    /**
     * Lookup NIB via API OSS publik.
     *
     * @param string $nib Nomor Induk Berusaha (13 digit).
     * @return array ['success' => bool, 'data' => [...], 'source' => 'oss'|'fallback']
     */
    public function lookupNib(string $nib): array
    {
        $nib = trim($nib);

        // Validasi format NIB (13 digit numeric).
        if (!preg_match('/^\d{11,16}$/', $nib)) {
            return [
                'success' => false,
                'error' => 'Format NIB tidak valid (harus 11-16 digit numeric).',
                'status_code' => 422,
            ];
        }

        // Coba API OSS publik bila dikonfigurasi.
        $ossUrl = config('ceisa.oss_api_url');
        if ($ossUrl) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders(['Accept' => 'application/json'])
                    ->get("{$ossUrl}/{$nib}");

                if ($response->ok()) {
                    $data = $response->json();
                    $nibData = $data['data'] ?? $data['nib'] ?? $data;

                    // Normalisasi response OSS ke format standar.
                    $normalized = [
                        'nib'      => $nib,
                        'npwp'     => $nibData['npwp'] ?? $nibData['NpwpPerseroan'] ?? null,
                        'nama'     => $nibData['namaPerseroan'] ?? $nibData['nama'] ?? $nibData['NamaPerseroan'] ?? null,
                        'alamat'   => $this->buildAddress($nibData),
                        'kelurahan'=> $nibData['kelurahan'] ?? $nibData['Kelurahan'] ?? null,
                        'kecamatan'=> $nibData['kecamatan'] ?? $nibData['Kecamatan'] ?? null,
                        'kabKota'  => $nibData['kabKota'] ?? $nibData['Kota'] ?? null,
                        'provinsi' => $nibData['provinsi'] ?? $nibData['Provinsi'] ?? null,
                        'kdPos'    => $nibData['kdPos'] ?? $nibData['KodePos'] ?? null,
                        'tlp'      => $nibData['tlp'] ?? $nibData['Telepon'] ?? null,
                    ];

                    if ($normalized['nama'] || $normalized['npwp']) {
                        return ['success' => true, 'data' => $normalized, 'source' => 'oss'];
                    }
                }
            } catch (\Throwable $e) {
                Log::info("OSS lookup failed for NIB {$nib}: {$e->getMessage()}");
            }
        }

        // Fallback: data demo (sandbox mode).
        // Dalam produksi, bila OSS terkonfigurasi, ini tidak akan tercapai.
        $fallback = $this->fallbackLookup($nib);
        return ['success' => true, 'data' => $fallback, 'source' => 'fallback'];
    }

    /** Build alamat lengkap dari komponen OSS response. */
    private function buildAddress(array $data): ?string
    {
        $parts = array_filter([
            $data['alamat'] ?? $data['Alamat'] ?? null,
            $data['kelurahan'] ?? $data['Kelurahan'] ?? null,
            $data['kecamatan'] ?? $data['Kecamatan'] ?? null,
            $data['kabKota'] ?? $data['Kota'] ?? null,
        ]);
        return !empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Fallback lookup — data demo untuk sandbox/testing.
     *
     * Bila NIB cocok dengan pattern demo, return data dummy yang realistis.
     * Bila tidak cocok, return data kosong (nama null) — frontend handle.
     */
    private function fallbackLookup(string $nib): array
    {
        // Data demo untuk testing.
        $demoData = [
            // NIB demo PT contoh
            '1234567890123' => [
                'nib'    => $nib,
                'npwp'   => '01.234.567.8-901.000',
                'nama'   => 'PT SAGANSA NUSANTARA',
                'alamat' => 'Jl. Sudirman No. 1, Karet, Setiabudi, Jakarta Selatan',
                'kelurahan' => 'Karet',
                'kecamatan' => 'Setiabudi',
                'kabKota'   => 'Jakarta Selatan',
                'provinsi'  => 'DKI Jakarta',
                'kdPos'     => '12920',
                'tlp'       => '021-1234567',
            ],
        ];

        if (isset($demoData[$nib])) {
            return $demoData[$nib];
        }

        // Bila bukan NIB demo, return struktur kosong (frontend handle).
        return [
            'nib'      => $nib,
            'npwp'     => null,
            'nama'     => null,
            'alamat'   => null,
            'kelurahan'=> null,
            'kecamatan'=> null,
            'kabKota'  => null,
            'provinsi' => null,
            'kdPos'    => null,
            'tlp'      => null,
        ];
    }
}
