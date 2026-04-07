<?php

namespace App\Services\Translation\DTO;

class SanitizationParametersDTO
{
    /**
     * @param  array<string, mixed>  $results
     * @param  array<int, string>  $allowedLocales
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly array $results,
        public readonly array $allowedLocales,
        public readonly string $originalText,
        public readonly array $context = [],
    ) {}
}
