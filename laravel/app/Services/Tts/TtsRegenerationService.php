<?php

namespace App\Services\Tts;

use App\Contracts\TtsAudioInterface;
use App\Helpers\ConfigHelper;
use App\Jobs\Tts\GenerateTtsAudioJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Manual, admin-triggered TTS regeneration for a single (field, locale).
 *
 * Complements — never replaces — the observer-driven auto-generation: the
 * observer fires on save when text changes, this gives a visible retry for the
 * cases the observer cannot cover (queue/quota failures, data imported or
 * hand-edited in the DB). Side effect is exactly one queued job; the heavy
 * lifting stays in GenerateTtsAudioJob so every dispatch source shares one path.
 *
 * Field/locale are validated against the model itself (only its real audio
 * attributes and the app's supported locales are accepted), surfacing a 422
 * rather than silently dispatching a no-op job.
 */
class TtsRegenerationService
{
    /**
     * @throws ValidationException
     */
    public function regenerate(TtsAudioInterface&Model $model, string $field, string $locale): void
    {
        $this->assertKnownField($model, $field);
        $this->assertSupportedLocale($locale);

        $text = $model->getTranslatableAttribute($model->getTtsSourceAttribute($field), $locale);

        if ($text === null || $text === '') {
            throw ValidationException::withMessages([
                'field' => __('validation.tts.empty_text'),
            ]);
        }

        GenerateTtsAudioJob::dispatch($model, $field, $locale);
    }

    /**
     * @throws ValidationException
     */
    private function assertKnownField(TtsAudioInterface&Model $model, string $field): void
    {
        if (! in_array($field, $model->getTtsAudioAttributes(), true)) {
            throw ValidationException::withMessages([
                'field' => __('validation.tts.unknown_field'),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function assertSupportedLocale(string $locale): void
    {
        if (! in_array($locale, ConfigHelper::getStringList('app.supported_locales', ['en']), true)) {
            throw ValidationException::withMessages([
                'locale' => __('validation.tts.unsupported_locale'),
            ]);
        }
    }
}
