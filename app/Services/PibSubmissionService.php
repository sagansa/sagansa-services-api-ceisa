<?php

namespace App\Services;

use App\Models\CeisaStatusHistory;
use App\Models\PibDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Fase 3 — Orkestrasi submit PIB ke CEISA 4.0 OpenAPI v2.
 *
 * BREAKING v2: Sejak OpenAPI v2, submit dokumen pabean (termasuk PIB/BC 2.0)
 * menggunakan endpoint unified:
 *
 *   POST /document?isFinal=&isRevision=
 *
 * Parameter query:
 *   - isFinal    : true = data langsung dikirim; false = data menjadi draft.
 *   - isRevision : true = data perbaikan (BCF); berlaku pada BC 3.0 dan TPB.
 *
 * Response v2 (lihat example OpenAPI v2 /document):
 *   200 OK   : { "status":"OK", "message":"...", "idHeader":"uuid" }
 *   400 FAIL : { "status":"FAILED", "message":"...", "nomorAju":"..." }
 *
 * Alur:
 * 1. Build payload via PibPayloadBuilder
 * 2. Validasi via PibSchemaValidator
 * 3. Kirim via CeisaClient ke POST /document?isFinal=true
 * 4. Simpan response (idHeader)
 * 5. Catat ke ceisa_status_histories
 */
class PibSubmissionService
{
    public function __construct(
        protected PibPayloadBuilder $builder,
        protected PibSchemaValidator $validator,
        protected CeisaClient $client,
    ) {
    }

    /**
     * Submit PIB ke gateway CEISA (v2 endpoint POST /document).
     *
     * @param bool $isFinal    true=langsung kirim (default), false=draft.
     * @param bool $isRevision true=data perbaikan (BCF).
     * @return array{success: bool, response_id: ?string, errors: array}
     */
    public function submit(PibDocument $doc, bool $isFinal = true, bool $isRevision = false): array
    {
        // 1) Build payload
        $payload = $this->builder->build($doc);

        // 2) Validasi lokal
        $validation = $this->validator->validate($doc);
        if (!$validation['valid']) {
            Log::warning('PIB submission failed local validation', [
                'pib_id' => $doc->id,
                'errors' => $validation['errors'],
            ]);

            return ['success' => false, 'response_id' => null, 'errors' => $validation['errors']];
        }

        // 3) Kirim ke gateway v2: POST /document?isFinal=&isRevision=
        try {
            $endpoint = (string) config('ceisa.endpoints.document_submit', '/document');
            $response = $this->client->post($endpoint, $payload, 'manifes', [
                'isFinal'    => $isFinal,
                'isRevision' => $isRevision,
            ]);
            $status = $response['status'];
            $body = $response['body'];

            // CeisaClient memakai http_errors=false, jadi kita evaluasi status code di sini.
            // v2: 200 OK → {status:"OK", idHeader}; 400 → {status:"FAILED", message}
            if ($status < 200 || $status >= 300) {
                $errMsg = $body['message']
                    ?? $body['error']
                    ?? $response['raw']
                    ?? "Gateway returned HTTP {$status}";

                Log::warning('PIB submission rejected by gateway', [
                    'pib_id' => $doc->id,
                    'http_status' => $status,
                    'gateway_body' => $body,
                ]);

                return [
                    'success' => false,
                    'response_id' => null,
                    'errors' => ["[HTTP {$status}] {$errMsg}"],
                ];
            }

            // v2 response: { status:"OK", message, idHeader }
            // backward-compat: cek juga field lama (responseId, id).
            $responseId = $body['idHeader']
                ?? $body['responseId']
                ?? $body['id']
                ?? null;
            $reference = $body['nomorPendaftaran']
                ?? $body['reference']
                ?? null;

            // 4) Simpan response ID / nomor pendaftaran
            $doc->update([
                'status' => $isFinal ? 'aju' : 'draft',
                'ceisa_response_id' => $responseId,
                'ceisa_reference' => $reference,
                'submitted_at' => now(),
            ]);

            CeisaStatusHistory::create([
                'pib_document_id' => $doc->id,
                'status' => $isFinal ? 'AJU' : 'DRAFT',
                'urgency' => 'normal',
                'raw_payload' => $body,
                'received_at' => now(),
            ]);

            return [
                'success' => true,
                'response_id' => $responseId,
                'errors' => [],
            ];
        } catch (\Throwable $e) {
            Log::error('PIB submission to gateway failed', [
                'pib_id' => $doc->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'response_id' => null,
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Submit sebagai draft (isFinal=false).
     *
     * @return array{success: bool, response_id: ?string, errors: array}
     */
    public function submitDraft(PibDocument $doc): array
    {
        return $this->submit($doc, isFinal: false);
    }

    /**
     * Submit sebagai perbaikan/BCF (isRevision=true, isFinal=true).
     *
     * @return array{success: bool, response_id: ?string, errors: array}
     */
    public function submitRevision(PibDocument $doc): array
    {
        return $this->submit($doc, isFinal: true, isRevision: true);
    }

    /**
     * Retry manual submission.
     */
    public function retry(int $docId): array
    {
        $doc = PibDocument::findOrFail($docId);

        return $this->submit($doc);
    }
}