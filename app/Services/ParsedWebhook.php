<?php

namespace App\Services;

/**
 * DTO hasil parse webhook CEISA.
 */
class ParsedWebhook
{
    public function __construct(
        public readonly ?string $ajuNumber,
        public readonly ?string $status,
        public readonly ?string $registrationNumber,
        public readonly string $urgency, // normal|urgent
        public readonly bool $hasNotul,
        public readonly array $raw,
    ) {
    }
}