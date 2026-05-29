<?php

namespace App\Contracts;

/**
 * Marks a Level subclass whose admin-editable text fields are stored as
 * Spatie translations. Implementations satisfy this contract automatically
 * by using Spatie\Translatable\HasTranslations.
 */
interface TranslatableLevelInterface
{
    /**
     * @return array<string, string>
     */
    public function getTranslations(string $attribute): array;
}
