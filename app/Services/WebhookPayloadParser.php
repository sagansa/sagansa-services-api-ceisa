<?php

namespace App\Services;

/**
 * Fase 4 — Parser payload webhook CEISA.
 *
 * Meng-ubah payload mentah menjadi DTO ringan.
 * Struktur field sebenarnya WAJIB diverifikasi via OpenAPI Portal; class ini
 * dirancang defensif (mencoba beberapa kemungkinan key).
 */
class WebhookPayloadParser
{
    /**
     * Format field di OpenAPI bisa berbeda; kita coba beberapa alias.
     */
    protected const AJU_KEYS = ['aju_number', 'nomorAju', 'nomor_aju', 'ajuNumber', 'number'];

    protected const STATUS_KEYS = ['status', 'status_code', 'statusCode', 'event', 'state'];

    protected const REG_KEYS = ['registration_number', 'nomor_pendaftaran', 'registrationNumber', 'np'];

    public function parse(array $payload): ParsedWebhook
    {
        $aju = $this->firstMatch($payload, self::AJU_KEYS);
        $status = strtoupper((string) $this->firstMatch($payload, self::STATUS_KEYS));
        $regNumber = $this->firstMatch($payload, self::REG_KEYS);

        // Deteksi NOTUL/SPTNP (bisa di field terpisah atau di items)
        $hasNotul = $this->detectNotul($payload, $status);
        $urgency = $this->classifyUrgency($status, $hasNotul);

        return new ParsedWebhook(
            ajuNumber: $aju,
            status: $status ?: null,
            registrationNumber: $regNumber,
            urgency: $urgency,
            hasNotul: $hasNotul,
            raw: $payload,
        );
    }

    protected function firstMatch(array $data, array $keys): mixed
    {
        foreach ($keys as $k) {
            if (!empty($data[$k])) {
                return $data[$k];
            }
        }

        return null;
    }

    /**
     * Deteksi NOTUL/SPTNP/underprice dari payload.
     */
    protected function detectNotul(array $payload, string $status): bool
    {
        $notulKeywords = ['NOTUL', 'SPTNP', 'SPP'];
        foreach ($notulKeywords as $kw) {
            if (str_contains($status, $kw)) {
                return true;
            }
        }

        // Cek field dokumen terpisah
        $docKeys = ['document', 'dokumen', 'notul', 'surat', 'documents'];
        foreach ($docKeys as $k) {
            if (!empty($payload[$k])) {
                $serialized = is_array($payload[$k]) ? json_encode($payload[$k]) : (string) $payload[$k];
                if (str_contains(strtoupper($serialized), 'NOTUL') || str_contains(strtoupper($serialized), 'SPTNP')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Klasifikasi urgensi (PRD 2.2).
     */
    protected function classifyUrgency(string $status, bool $hasNotul): string
    {
        $urgent = (array) config('ceisa.urgency.urgent', []);
        $normal = (array) config('ceisa.urgency.normal', []);

        if ($hasNotul) {
            return 'urgent';
        }
        foreach ($urgent as $u) {
            if (str_contains($status, strtoupper($u))) {
                return 'urgent';
            }
        }
        foreach ($normal as $n) {
            if (str_contains($status, strtoupper($n))) {
                return 'normal';
            }
        }

        return 'normal';
    }
}