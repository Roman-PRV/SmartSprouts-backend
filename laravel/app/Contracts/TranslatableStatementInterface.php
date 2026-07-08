<?php

namespace App\Contracts;

/**
 * Marks a true/false statement model whose editable text fields are stored as
 * Spatie translations. Both games' statement models satisfy this automatically
 * by using Spatie\Translatable\HasTranslations, letting the shared admin
 * service and resource operate on either without a concrete dependency.
 */
interface TranslatableStatementInterface
{
    /**
     * @return array<string, string>
     */
    public function getTranslations(string $attribute): array;

    /**
     * @param  mixed  $value
     * @return $this
     */
    public function setTranslation(string $key, string $locale, $value): self;
}
