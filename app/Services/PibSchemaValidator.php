<?php

namespace App\Services;

use App\Models\PibDocument;
use Illuminate\Support\Facades\Validator;

/**
 * Fase 3 — Validator lokal schema PIB sebelum kirim ke gateway.
 * Tujuan: minim error dari Bea Cukai.
 *
 * Aturan field WAJIB diverifikasi via OpenAPI Portal.
 */
class PibSchemaValidator
{
    /**
     * @return array{valid: bool, errors: array}
     */
    public function validate(PibDocument $doc): array
    {
        $payload = app(PibPayloadBuilder::class)->build($doc);

        $validator = Validator::make($payload, [
            'header.nomorAju' => ['required', 'string'],
            'header.kantorPabean' => ['required', 'string'],
            'header.npwpImportir' => ['required', 'string', 'regex:/^[0-9]{15,16}$/'],
            'header.namaImportir' => ['required', 'string'],
            'header.jenisTransaksi' => ['required', 'string'],
            'header.saranaAngkut' => ['required', 'string'],
            'header.pelabuhanMuat' => ['required', 'string'],
            'header.pelabuhanBongkar' => ['required', 'string'],
            'detailBarang' => ['required', 'array', 'min:1'],
            'detailBarang.*.seri' => ['required', 'integer', 'min:1'],
            'detailBarang.*.hsCode' => ['required', 'string'],
            'detailBarang.*.uraian' => ['required', 'string'],
            'detailBarang.*.nilaiCif' => ['required', 'numeric', 'min:0'],
            'detailBarang.*.beaMasuk' => ['required', 'numeric', 'min:0'],
            'keuangan.valuationDeclaration' => ['required', 'numeric', 'min:0'],
        ]);

        return [
            'valid' => !$validator->fails(),
            'errors' => $validator->errors()->all(),
        ];
    }
}