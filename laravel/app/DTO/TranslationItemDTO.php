<?php

namespace App\DTO;

use App\Enums\TranslationStatusEnum;

class TranslationItemDTO
{
    public function __construct(
        public readonly TranslationStatusEnum $status,
        public readonly ?string $text = null,
        public readonly ?string $fallback = null,
    ) {}
}
