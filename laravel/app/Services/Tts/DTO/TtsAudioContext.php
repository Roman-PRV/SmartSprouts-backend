<?php

namespace App\Services\Tts\DTO;

use App\Contracts\TtsAudioInterface;
use Illuminate\Database\Eloquent\Model;

readonly class TtsAudioContext
{
    public function __construct(
        public TtsAudioInterface&Model $model,
        public string $attribute,
        public string $locale,
        public ?string $text = null,
    ) {}

    public static function make(TtsAudioInterface&Model $model, string $attribute, string $locale, ?string $text = null): self
    {
        return new self($model, $attribute, $locale, $text);
    }

    public function getModel(): TtsAudioInterface&Model
    {
        return $this->model;
    }

    public function getAttribute(): string
    {
        return $this->attribute;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function getText(): ?string
    {
        return $this->text;
    }
}
