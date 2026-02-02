<?php

namespace App\DTO;

class TranslationResultDTO
{
    /**
     * @param  array<string, TranslationItemDTO>  $translations
     */
    public function __construct(
        public readonly array $translations,
        public readonly string $requestId,
    ) {}
}
