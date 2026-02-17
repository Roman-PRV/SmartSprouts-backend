<?php

namespace App\Services\Tts;

use App\Contracts\TtsProviderInterface;
use App\Enums\Tts\TtsModelMappingEnum;
use App\Helpers\ConfigHelper;
use App\Services\Tts\DTO\TtsAudioContext;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\DTO\TtsResultDTO;
use Psr\Log\LoggerInterface;

class TtsAudioGeneratorService
{
    private const FALLBACK_GAME_TYPE = 'unknown';

    private const FALLBACK_ENTITY_TYPE = 'unknown';

    private const HASH_LENGTH = 8;

    public function __construct(
        private readonly TtsProviderInterface $ttsProvider,
        private readonly TtsStorageService $storageService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Generate TTS audio for a model attribute and locale.
     *
     * @param  TtsAudioContext  $context  The TTS audio context
     * @return string|null The generated audio URL or null on failure
     */
    public function generateForModel(TtsAudioContext $context): ?string
    {
        try {
            $text = $context->getText() ?? $this->extractTextContent($context);

            if (! $this->validateText($text, $context)) {
                return null;
            }

            /** @var string|null $path */
            $path = $this->resolveAudioPath($context);

            if ($path) {
                return $path;
            }

            return $this->synthesizeAndStore($context);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to generate TTS audio', [
                ...$context->toLogContext(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Extract text content from the context.
     */
    private function extractTextContent(TtsAudioContext $context): ?string
    {
        return $context->getModel()->getTtsText($context->getAttribute(), $context->getLocale());
    }

    /**
     * Generate storage path for audio file.
     */
    private function generateStoragePath(TtsAudioContext $context, string $format): string
    {
        $model = $context->getModel();
        $mapping = TtsModelMappingEnum::fromModel($model);

        $gameType = $mapping?->getGameType() ?? self::FALLBACK_GAME_TYPE;
        $entityType = $mapping?->getEntityType() ?? self::FALLBACK_ENTITY_TYPE;

        $attributeBaseName = $model->getTtsSourceAttribute($context->getAttribute());

        $text = $context->getText() ?? '';
        $hash = substr(md5($text.$context->getLocale()), 0, self::HASH_LENGTH);

        /** @var int $id */
        $id = $model->getKey();

        return sprintf(
            '%s/%s/%s/%s/%s/%s_%s.%s',
            ConfigHelper::getString('ai.tts.storage.path_prefix', 'games'),
            $gameType,
            $entityType,
            $id,
            $context->getLocale(),
            $attributeBaseName,
            $hash,
            $format
        );
    }

    /**
     * Resolve audio path for model attribute.
     */
    private function resolveAudioPath(TtsAudioContext $context): ?string
    {
        $expectedFormat = ConfigHelper::getString('ai.tts.output_format', 'mp3');
        $path = $this->generateStoragePath($context, $expectedFormat);

        if ($this->storageService->exists($path)) {
            $this->logger->info('TTS audio file already exists for model, skipping generation', [
                ...$context->toLogContext(),
                'path' => $path,
            ]);
            $this->updateModelAudioUrl($context, $path);

            return $path;
        }

        return null;
    }

    /**
     * Synthesize audio and store it.
     */
    private function synthesizeAndStore(TtsAudioContext $context): string
    {
        $text = $context->getText() ?? '';
        $request = new TtsRequestDTO(text: $text);

        $result = $this->ttsProvider->synthesize($request);
        // $result = new TtsResultDTO(audioData: 'test', format: 'mp3');

        $path = $this->generateStoragePath($context, $result->format);
        $this->storageService->storeWithPath($result, $path);
        $this->updateModelAudioUrl($context, $path);

        $this->logger->info('TTS audio generated successfully', [
            ...$context->toLogContext(),
            'path' => $path,
        ]);

        return $path;
    }

    /**
     * Update the model's audio URL attribute.
     */
    private function updateModelAudioUrl(TtsAudioContext $context, string $path): void
    {
        $context->getModel()->setAudioPath(
            $context->getAttribute(),
            $context->getLocale(),
            $path
        );
    }

    /**
     * Validate text content.
     *
     * @phpstan-assert-if-true string $text
     */
    private function validateText(?string $text, TtsAudioContext $context): bool
    {
        if (! $text) {
            $this->logger->warning('No text content found for TTS generation', $context->toLogContext());

            return false;
        }

        return true;
    }
}
