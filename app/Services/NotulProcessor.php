<?php

namespace App\Services;

use App\Models\CeisaNotulDocument;
use App\Models\PibDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

/**
 * Fitur 3a — NOTUL/SPTNP & Underprice processor.
 *
 * Tanggung jawab:
 * 1. Parse dokumen NOTUL (nomor surat, tanggal, HS, nilai deklarasi vs penetapan).
 * 2. Deteksi underprice (nilai_penetapan_bc > nilai_deklarasi).
 * 3. Hitung selisih bea masuk, denda, total kewajiban.
 * 4. Simpan ke ceisa_notul_documents.
 * 5. Download PDF NOTUL (opsional).
 * 6. Set due_date_ssp.
 */
class NotulProcessor
{
    /**
     * Proses payload NOTUL & simpan hasilnya.
     */
    public function process(array $payload, PibDocument $pibDoc): CeisaNotulDocument
    {
        $data = $this->extractNotulData($payload);

        // Hitung selisih & kewajiban
        $selisih = $this->calculateSelisih($data['nilai_deklarasi'], $data['nilai_penetapan_bc']);
        $totalKewajiban = $this->calculateTotalKewajiban($selisih, $data['denda'], $data['ppn_pph']);
        $isUnderprice = $this->isUnderprice($data['nilai_deklarasi'], $data['nilai_penetapan_bc']);

        // Download PDF bila ada URL lampiran
        $filePath = null;
        if (!empty($data['pdf_url'])) {
            $filePath = $this->downloadPdf($data['pdf_url'], $pibDoc, $data['nomor_surat']);
        }

        // Simpan NOTUL
        $notul = CeisaNotulDocument::create([
            'pib_document_id' => $pibDoc->id,
            'nomor_surat' => $data['nomor_surat'],
            'tanggal_surat' => $data['tanggal_surat'],
            'hs_code' => $data['hs_code'],
            'uraian_barang' => $data['uraian_barang'],
            'nilai_deklarasi' => $data['nilai_deklarasi'],
            'nilai_penetapan_bc' => $data['nilai_penetapan_bc'],
            'selisih_bea_masuk' => $selisih,
            'denda' => $data['denda'],
            'ppn_pph' => $data['ppn_pph'],
            'total_kewajiban' => $totalKewajiban,
            'is_underprice' => $isUnderprice,
            'jenis_surat' => $data['jenis_surat'],
            'due_date_ssp' => $data['due_date_ssp'],
            'rekening_ssp' => $data['rekening_ssp'],
            'file_path' => $filePath,
            'raw_payload' => $payload,
        ]);

        // Update flag underprice di PIB header
        if ($isUnderprice) {
            $pibDoc->update([
                'is_underprice' => true,
                'due_date_ssp' => $data['due_date_ssp'] ?? $pibDoc->due_date_ssp,
                'status' => 'notul',
            ]);
        }

        return $notul;
    }

    /**
     * Ekstrak data NOTUL dari payload (defensif untuk berbagai format).
     */
    protected function extractNotulData(array $payload): array
    {
        $notul = $payload['notul']
            ?? $payload['document']
            ?? $payload['dokumen']
            ?? $payload['surat']
            ?? $payload;

        return [
            'nomor_surat' => $notul['nomor_surat'] ?? $notul['nomorSurat'] ?? $notul['number'] ?? null,
            'tanggal_surat' => $this->parseDate($notul['tanggal_surat'] ?? $notul['tanggalSurat'] ?? $notul['date'] ?? null),
            'hs_code' => $notul['hs_code'] ?? $notul['hsCode'] ?? $notul['kode_hs'] ?? null,
            'uraian_barang' => $notul['uraian_barang'] ?? $notul['uraianBarang'] ?? $notul['description'] ?? null,
            'nilai_deklarasi' => (float) ($notul['nilai_deklarasi'] ?? $notul['nilaiDeklarasi'] ?? $notul['declaration_value'] ?? 0),
            'nilai_penetapan_bc' => (float) ($notul['nilai_penetapan_bc'] ?? $notul['nilaiPenetapanBc'] ?? $notul['assigned_value'] ?? 0),
            'denda' => (float) ($notul['denda'] ?? $notul['penalty'] ?? 0),
            'ppn_pph' => (float) ($notul['ppn_pph'] ?? $notul['ppnPph'] ?? $notul['tax'] ?? 0),
            'jenis_surat' => strtoupper($notul['jenis_surat'] ?? $notul['jenisSurat'] ?? 'NOTUL'),
            'due_date_ssp' => $this->parseDate($notul['due_date_ssp'] ?? $notul['dueDateSsp'] ?? $notul['jatuh_tempo'] ?? null),
            'rekening_ssp' => $notul['rekening_ssp'] ?? $notul['rekeningSsp'] ?? $notul['billing_code'] ?? null,
            'pdf_url' => $notul['pdf_url'] ?? $notul['pdfUrl'] ?? $notul['file_url'] ?? null,
        ];
    }

    /**
     * Hitung selisih bea masuk (penetapan - deklarasi).
     */
    public function calculateSelisih(float $deklarasi, float $penetapan): float
    {
        return round($penetapan - $deklarasi, 2);
    }

    /**
     * Total kewajiban = selisih bea masuk + denda + PPN/PPH.
     */
    public function calculateTotalKewajiban(float $selisih, float $denda, float $ppnPph): float
    {
        return round($selisih + $denda + $ppnPph, 2);
    }

    /**
     * Underprice jika nilai penetapan BC > nilai deklarasi.
     */
    public function isUnderprice(float $deklarasi, float $penetapan): bool
    {
        return $penetapan > $deklarasi;
    }

    /**
     * Ambil total kewajiban dari dokumen NOTUL.
     */
    public function getTotalKewajiban(CeisaNotulDocument $notulDoc): float
    {
        return $this->calculateTotalKewajiban(
            (float) $notulDoc->selisih_bea_masuk,
            (float) $notulDoc->denda,
            (float) $notulDoc->ppn_pph,
        );
    }

    /**
     * Download PDF NOTUL ke storage/app/notul/.
     */
    protected function downloadPdf(string $url, PibDocument $pibDoc, ?string $nomorSurat): ?string
    {
        try {
            $contents = file_get_contents($url);
            if ($contents === false) {
                return null;
            }
            $filename = sprintf(
                'notul/%s_%s.pdf',
                $pibDoc->aju_number ?? $pibDoc->id,
                str_replace(['/', ' '], '_', (string) $nomorSurat ?: 'surat'),
            );
            Storage::disk('local')->put($filename, $contents);

            return $filename;
        } catch (\Throwable $e) {
            Log::warning('Failed to download NOTUL PDF', ['url' => $url, 'error' => $e->getMessage()]);

            return null;
        }
    }

    protected function parseDate(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}