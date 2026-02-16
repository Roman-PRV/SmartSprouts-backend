<?php

namespace App\Services\Tts;

use App\Events\TtsAudioRequestedEvent;
use App\Helpers\MediaHelper;
use App\Services\Tts\DTO\TtsAudioContext;
use Illuminate\Support\Facades\Log;
use Throwable;

class TtsOrchestrator
{
    /**
     * Get the absolute URL for a media attribute, or trigger generation if missing.
     *
     * @param  TtsAudioContext  $context  The TTS audio context
     * @return string|null The absolute URL or null
     */
    public function getOrGenerate(TtsAudioContext $context): ?string
    {
        $model = $context->getModel();
        $attribute = $context->getAttribute();
        $locale = $context->getLocale();

        /** @var string|null $audioPath */
        $audioPath = $model->getTranslatableAttribute($attribute, $locale);

        if ($this->shouldGenerateAudio($audioPath)) {
            $this->dispatchGeneration($context);
        }

        return MediaHelper::getUrl($audioPath);
    }

    /**
     * Dispatch event to generate missing audio.
     */
    private function dispatchGeneration(TtsAudioContext $context): void
    {
        try {
            $model = $context->getModel();
            $attribute = $context->getAttribute();
            $locale = $context->getLocale();

            $text = $model->getTtsText($attribute, $locale);

            if (! $text) {
                return;
            }

            Log::channel('tts')->info('Audio URL is missing, dispatching TTS generation event', $context->toLogContext());

            TtsAudioRequestedEvent::dispatch(
                TtsAudioContext::make($model, $attribute, $locale, $text)
            );

        } catch (Throwable $e) {
            Log::channel('tts')->error('Error dispatching TTS generation event', [
                ...$context->toLogContext(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if audio should be generated.
     */
    private function shouldGenerateAudio(?string $audioPath): bool
    {
        return (empty($audioPath)) && config('ai.tts.auto_generate.enabled', true);
    }
}
