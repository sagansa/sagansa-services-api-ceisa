<?php

namespace App\Services;

use App\Models\PibDocument;

/**
 * Fase 3 — Builder payload JSON PIB sesuai schema CEISA.
 *
 * Mengubah PibDocument + relasi menjadi JSON yang siap dikirim ke gateway.
 * Struktur field WAJIB diverifikasi via OpenAPI Portal.
 */
class PibPayloadBuilder
{
    /**
     * Build full PIB payload dari model + relasi.
     */
    public function build(PibDocument $doc): array
    {
        $doc->load(['items', 'supportingDocs']);

        return [
            'header' => $this->buildHeader($doc),
            'detailBarang' => $this->buildItems($doc),
            'dokumen' => $this->buildSupportingDocs($doc),
            'keuangan' => $this->buildFinancials($doc),
        ];
    }

    /**
     * Header PIB: nomor aju, kantor pabean, importir, PPJK, dll.
     */
    public function buildHeader(PibDocument $doc): array
    {
        return [
            'nomorAju' => $doc->aju_number,
            'kantorPabean' => $doc->kantor_pabean,
            'npwpImportir' => $this->normalizeNpwp($doc->importir_npwp),
            'namaImportir' => $doc->importir_name,
            'npwpPpjk' => $this->normalizeNpwp($doc->ppjk_npwp),
            'jenisTransaksi' => $doc->jenis_transaksi,
            'saranaAngkut' => $doc->sarana_angkut,
            'pelabuhanMuat' => $doc->pelabuhan_muat,
            'pelabuhanBongkar' => $doc->pelabuhan_bongkar,
        ];
    }

    /**
     * Normalize NPWP: strip non-digit characters (dots, dashes, spaces).
     * CEISA expects raw digits (15-16 chars), e.g. "012345678901000".
     */
    private function normalizeNpwp(?string $npwp): ?string
    {
        if ($npwp === null || $npwp === '') {
            return $npwp;
        }

        return preg_replace('/\D/', '', $npwp);
    }

    /**
     * Detil barang (items): seri, HS code, uraian, nilai CIF, bea masuk, PPN/PPH.
     */
    public function buildItems(PibDocument $doc): array
    {
        return $doc->items->map(fn ($item) => [
            'seri' => $item->seri,
            'hsCode' => $item->hs_code,
            'uraian' => $item->uraian_barang,
            'negaraAsal' => $item->negara_asal,
            'jumlahSatuan' => (float) $item->jumlah_satuan,
            'satuan' => $item->satuan,
            'nilaiCif' => (float) $item->nilai_cif,
            'beaMasuk' => (float) $item->bea_masuk,
            'ppn' => (float) $item->ppn,
            'pph' => (float) $item->pph,
        ])->all();
    }

    /**
     * Dokumen pendukung: invoice, packing list, BL/AWB, COO, manifest.
     */
    public function buildSupportingDocs(PibDocument $doc): array
    {
        return $doc->supportingDocs->map(fn ($doc2) => [
            'type' => $doc2->type,
            'fileName' => $doc2->original_name,
            'filePath' => $doc2->file_path,
            'mimeType' => $doc2->mime_type,
        ])->all();
    }

    /**
     * Data keuangan: valuation declaration.
     */
    public function buildFinancials(PibDocument $doc): array
    {
        return [
            'valuationDeclaration' => (float) $doc->valuation_declaration,
        ];
    }
}