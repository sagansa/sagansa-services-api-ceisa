<?php

namespace App\Services;

/**
 * Respon Service — CEISA 4.0 OpenAPI v2 (respon & PDF endpoints).
 *
 * Membungkus endpoint respon-controller di gateway BC yang mengembalikan
 * dokumen formal dalam format PDF (binary):
 *
 *   GET /respon/pdf?kodeRespon=&nomorAju=            → PDF Respon
 *   GET /respon/billing?kodeBilling=                 → Billing/tunggakan PDF
 *   GET /respon/cetak-formulir?nomorAju=             → Formulir resmi
 *   GET /respon/cetak-formulir/draft?nomorAju=       → Formulir draft
 *   GET /respon/cetak-formulir/final?nomorAju=       → Formulir final
 *   GET /respon/npe-bc33/{kodeDokumen}/{tanggalDokumen}?kodeGudang= → NPE BC 3.3
 *   GET /download-respon?path=                       → Download PDF Respon (by path)
 *
 * Berbeda dari service JSON (StatusService, dll), service ini mengembalikan
 * RAW BINARY content via CeisaClient::getRaw() karena endpoint BC mengirim
 * file PDF, bukan JSON.
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: respon-controller.
 */
class ResponService extends CeisaBaseService
{
    /**
     * GET /respon/pdf — PDF Respon dokumen.
     *
     * @param string      $kodeRespon  Kode respon (mis. "0101" untuk SPPB).
     * @param string      $nomorAju    Nomor Aju dokumen.
     * @return array{success:bool, status_code:int, content:string, content_type:string, filename:string, error:?string}
     */
    public function pdf(string $kodeRespon, string $nomorAju): array
    {
        $path = $this->endpoint('respon_pdf');
        $result = $this->client->getRaw($path, [
            'kodeRespon' => $kodeRespon,
            'nomorAju'   => $nomorAju,
        ]);

        return $this->wrapBinary($result, "respon-{$kodeRespon}-{$nomorAju}.pdf");
    }

    /**
     * GET /respon/billing — PDF Billing/tunggakan bea.
     *
     * @param string $kodeBilling Kode billing dari BC.
     */
    public function billing(string $kodeBilling): array
    {
        $path = $this->endpoint('respon_billing');
        $result = $this->client->getRaw($path, [
            'kodeBilling' => $kodeBilling,
        ]);

        return $this->wrapBinary($result, "billing-{$kodeBilling}.pdf");
    }

    /**
     * GET /respon/cetak-formulir — PDF Formulir resmi.
     */
    public function formulir(string $nomorAju): array
    {
        $path = $this->endpoint('respon_cetak_formulir');
        $result = $this->client->getRaw($path, [
            'nomorAju' => $nomorAju,
        ]);

        return $this->wrapBinary($result, "formulir-{$nomorAju}.pdf");
    }

    /**
     * GET /respon/cetak-formulir/draft — PDF Formulir draft.
     */
    public function formulirDraft(string $nomorAju): array
    {
        $path = $this->endpoint('respon_formulir_draft');
        $result = $this->client->getRaw($path, [
            'nomorAju' => $nomorAju,
        ]);

        return $this->wrapBinary($result, "formulir-draft-{$nomorAju}.pdf");
    }

    /**
     * GET /respon/cetak-formulir/final — PDF Formulir final.
     */
    public function formulirFinal(string $nomorAju): array
    {
        $path = $this->endpoint('respon_formulir_final');
        $result = $this->client->getRaw($path, [
            'nomorAju' => $nomorAju,
        ]);

        return $this->wrapBinary($result, "formulir-final-{$nomorAju}.pdf");
    }

    /**
     * GET /respon/npe-bc33/{kodeDokumen}/{tanggalDokumen} — NPE BC 3.3 (ekspor).
     *
     * @param string $kodeDokumen    Kode dokumen (mis. "33").
     * @param string $tanggalDokumen Format YYYY-MM-DD.
     * @param string $kodeGudang     Kode gudang TPB.
     */
    public function npeBc33(string $kodeDokumen, string $tanggalDokumen, string $kodeGudang): array
    {
        $path = $this->endpoint('respon_npe_bc33', [
            'kodeDokumen'    => $kodeDokumen,
            'tanggalDokumen' => $tanggalDokumen,
        ]);
        $result = $this->client->getRaw($path, [
            'kodeGudang' => $kodeGudang,
        ]);

        return $this->wrapBinary($result, "npe-bc33-{$kodeDokumen}-{$tanggalDokumen}.pdf");
    }

    /**
     * GET /download-respon?path= — Download PDF Respon by path.
     *
     * Dipakai bila BC memberi path spesifik ke PDF (mis. dari field respon).
     *
     * @param string $pdfPath Path PDF yang diberikan BC.
     */
    public function download(string $pdfPath): array
    {
        $path = $this->endpoint('download_respon');
        $result = $this->client->getRaw($path, [
            'path' => $pdfPath,
        ]);

        // Filename dari path BC (fallback generic).
        $basename = basename($pdfPath);
        $filename = $basename !== '' && $basename !== '/'
            ? $basename
            : 'respon-download.pdf';

        return $this->wrapBinary($result, $filename);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Bungkus hasil getRaw() ke format standar binary response.
     *
     * @param array  $result   Hasil CeisaClient::getRaw (status, content, content_type).
     * @param string $filename Nama file default untuk download.
     */
    protected function wrapBinary(array $result, string $filename): array
    {
        $status = $result['status'] ?? 0;
        $content = $result['content'] ?? '';
        $success = $status >= 200 && $status < 300 && $content !== '';

        return [
            'success'      => $success,
            'status_code'  => $status,
            'content'      => $content,
            'content_type' => $result['content_type'] ?? 'application/pdf',
            'filename'     => $filename,
            'endpoint'     => $result['endpoint'] ?? null,
            'error'        => $success ? null : "Gagal mengambil PDF (HTTP {$status})",
        ];
    }
}
