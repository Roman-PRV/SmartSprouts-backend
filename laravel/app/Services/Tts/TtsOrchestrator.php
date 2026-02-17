<?php

namespace App\Services\Tts;

use App\Contracts\Media\MediaUrlGeneratorInterface;
use App\Events\TtsAudioRequestedEvent;
use App\Services\Tts\DTO\TtsAudioContext;
use Psr\Log\LoggerInterface;
use Throwable;

class TtsOrchestrator
{
    public function __construct(
        private readonly MediaUrlGeneratorInterface $mediaUrlGenerator,
        private readonly LoggerInterface $logger,
        private readonly bool $autoGenerateEnabled = true,
        private readonly string $ttsDisk = 'public',
    ) {}

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

        return $this->mediaUrlGenerator->getUrl($audioPath, $this->ttsDisk);
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

            $this->logger->info('Audio URL is missing, dispatching TTS generation event', $context->toLogContext());

            TtsAudioRequestedEvent::dispatch(
                TtsAudioContext::make($model, $attribute, $locale, $text)
            );

        } catch (Throwable $e) {
            $this->logger->error('Error dispatching TTS generation event', [
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
        return (empty($audioPath)) && $this->autoGenerateEnabled;
    }
}
