<?php

namespace App\Services;

/**
 * File Service — CEISA 4.0 OpenAPI v2 (file endpoints).
 *
 * Upload dokumen pendukung ke gateway BC:
 *
 *   POST /file/barang           → upload file barang (detil items)
 *   POST /file/dokumen          → upload file dokumen (invoice, BL, PL)
 *   POST /file/upload-dokap-npd → upload file DOKAP/NPD
 *
 * Dipakai saat PIB submission untuk melampirkan dokumen pendukung.
 *
 * Sumber: doc/json/Export_openapi_v2_*.json — tags: file-controller.
 */
class FileService extends CeisaBaseService
{
    /**
     * POST /file/barang — Upload file barang (detil items PIB).
     *
     * @param  string $binaryContent Raw file content (PDF/image bytes).
     * @param  string $filename      Nama file asli (mis. "items-detail.xlsx").
     * @param  string $contentType   MIME type (mis. "application/pdf").
     * @param  array  $formFields    Field form tambahan (mis. nomorAju, kodeDokumen).
     */
    public function uploadBarang(string $binaryContent, string $filename, string $contentType = 'application/pdf', array $formFields = []): array
    {
        $path = $this->endpoint('file_barang');

        return $this->client->postMultipart($path, [
            ['name' => 'file', 'contents' => $binaryContent, 'filename' => $filename, 'contentType' => $contentType],
        ], $formFields);
    }

    /**
     * POST /file/dokumen — Upload file dokumen pendukung (invoice, BL, packing list).
     */
    public function uploadDokumen(string $binaryContent, string $filename, string $contentType = 'application/pdf', array $formFields = []): array
    {
        $path = $this->endpoint('file_dokumen');

        return $this->client->postMultipart($path, [
            ['name' => 'file', 'contents' => $binaryContent, 'filename' => $filename, 'contentType' => $contentType],
        ], $formFields);
    }

    /**
     * POST /file/upload-dokap-npd — Upload file DOKAP/NPD.
     */
    public function uploadDokapNpd(string $binaryContent, string $filename, string $contentType = 'application/pdf', array $formFields = []): array
    {
        $path = $this->endpoint('file_upload_dokap_npd');

        return $this->client->postMultipart($path, [
            ['name' => 'file', 'contents' => $binaryContent, 'filename' => $filename, 'contentType' => $contentType],
        ], $formFields);
    }
}
