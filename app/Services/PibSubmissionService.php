<?php

namespace App\Services;

use App\Models\CeisaStatusHistory;
use App\Models\PibDocument;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Fase 3 — Orkestrasi submit PIB ke CEISA 4.0.
 *
 * Alur:
 * 1. Build payload via PibPayloadBuilder
 * 2. Validasi via PibSchemaValidator
 * 3. Kirim via CeisaClient
 * 4. Simpan response (Response ID / nomor pendaftaran)
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
     * Submit PIB ke gateway CEISA.
     *
     * @return array{success: bool, response_id: ?string, errors: array}
     */
    public function submit(PibDocument $doc): array
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

        // 3) Kirim ke gateway (endpoint path WAJIB diverifikasi via OpenAPI)
        try {
            // CATATAN: path submit PIB harus dikonfirmasi dari OpenAPI Portal.
            // Placeholder: /v1/pib/submit
            $response = $this->client->post('/v1/pib/submit', $payload);
            $body = $response['body'];

            // 4) Simpan response ID / nomor pendaftaran
            $responseId = $body['responseId'] ?? $body['id'] ?? null;
            $reference = $body['nomorPendaftaran'] ?? $body['reference'] ?? null;

            $doc->update([
                'status' => 'aju',
                'ceisa_response_id' => $responseId,
                'ceisa_reference' => $reference,
                'submitted_at' => now(),
            ]);

            CeisaStatusHistory::create([
                'pib_document_id' => $doc->id,
                'status' => 'AJU',
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
     * Retry manual submission.
     */
    public function retry(int $docId): array
    {
        $doc = PibDocument::findOrFail($docId);

        return $this->submit($doc);
    }
}