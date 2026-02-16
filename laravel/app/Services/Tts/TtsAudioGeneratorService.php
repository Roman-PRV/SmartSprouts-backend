<?php

namespace App\Services\Tts;

use App\Contracts\TtsAudioInterface;
use App\Contracts\TtsProviderInterface;
use App\Enums\Tts\TtsModelMappingEnum;
use App\Helpers\ConfigHelper;
use App\Services\Tts\DTO\TtsAudioContext;
use App\Services\Tts\DTO\TtsRequestDTO;
use App\Services\Tts\DTO\TtsResultDTO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Storage;

class TtsAudioGeneratorService
{
    private const FALLBACK_GAME_TYPE = 'unknown';

    private const FALLBACK_ENTITY_TYPE = 'unknown';

    public function __construct(
        private readonly TtsProviderInterface $ttsProvider,
        private readonly TtsStorageService $storageService,
    ) {}

    /**
     * Generate TTS audio for a model attribute and locale.
     *
     * @param  TtsAudioContext  $context  The TTS audio context
     * @return string|null The generated audio URL or null on failure
     */
    public function generateForModel(TtsAudioContext $context): ?string
    {
        try {
            $model = $context->getModel();
            $attribute = $context->getAttribute();
            $locale = $context->getLocale();

            $text = $context->getText() ?? $this->extractTextContent($model, $attribute, $locale);

            if (! $this->validateText($text, $model, $attribute, $locale)) {
                return null;
            }

            /** @var string|null $path */
            $path = $this->resolveAudioPath($model, $attribute, $locale, $text);

            if ($path) {
                return $path;
            }

            return $this->synthesizeAndStore($model, $attribute, $locale, $text);

        } catch (\Throwable $e) {
            Log::error('Failed to generate TTS audio', [
                'model' => get_class($context->getModel()),
                'id' => $context->getModel()->getKey(),
                'attribute' => $context->getAttribute(),
                'locale' => $context->getLocale(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Check if audio file exists for model attribute.
     */
    private function audioExists(string $path): bool
    {
        $disk = ConfigHelper::getString('ai.tts.storage.disk', 'public');

        return Storage::disk($disk)->exists($path);

    }

    /**
     * Extract text content from model attribute.
     */
    private function extractTextContent(TtsAudioInterface&Model $model, string $attribute, string $locale): ?string
    {
        return $model->getTtsText($attribute, $locale);
    }

    /**
     * Generate storage path for audio file.
     */
    private function generateStoragePath(TtsAudioInterface&Model $model, string $attribute, string $locale, string $format, string $text): string
    {
        $mapping = TtsModelMappingEnum::fromModel($model);

        $gameType = $mapping?->getGameType() ?? self::FALLBACK_GAME_TYPE;
        $entityType = $mapping?->getEntityType() ?? self::FALLBACK_ENTITY_TYPE;

        $attributeBaseName = $model->getTtsSourceAttribute($attribute);

        $hash = substr(md5($text.$locale), 0, 8);

        /** @var int $id */
        $id = $model->getKey();

        return sprintf(
            '%s/%s/%s/%s/%s/%s_%s.%s',
            ConfigHelper::getString('ai.tts.storage.path_prefix', 'games'),
            $gameType,
            $entityType,
            $id,
            $locale,
            $attributeBaseName,
            $hash,
            $format
        );
    }

    /**
     * Resolve audio path for model attribute.
     */
    private function resolveAudioPath(TtsAudioInterface&Model $model, string $attribute, string $locale, string $text): ?string
    {
        $expectedFormat = ConfigHelper::getString('ai.tts.output_format', 'mp3');
        $path = $this->generateStoragePath($model, $attribute, $locale, $expectedFormat, $text);

        if ($this->audioExists($path)) {
            Log::info('TTS audio file already exists for model, skipping generation', [
                'path' => $path,
            ]);
            $this->updateModelAudioUrl($model, $attribute, $locale, $path);

            return $this->storageService->getUrl($path);
        }

        return null;
    }

    /**
     * Synthesize audio and store it.
     */
    private function synthesizeAndStore(TtsAudioInterface&Model $model, string $attribute, string $locale, string $text): string
    {
        $request = new TtsRequestDTO(text: $text);
        // $result = $this->ttsProvider->synthesize($request);
        $result = new TtsResultDTO(audioData: 'test', format: 'mp3');

        $path = $this->generateStoragePath($model, $attribute, $locale, $result->format, $text);
        $this->storageService->storeWithPath($result, $path);
        $this->updateModelAudioUrl($model, $attribute, $locale, $path);

        Log::info('TTS audio generated successfully', [
            'model' => get_class($model),
            'id' => $model->getKey(),
            'attribute' => $attribute,
            'locale' => $locale,
            'path' => $path,
        ]);

        return $this->storageService->getUrl($path);
    }

    /**
     * Update the model's audio URL attribute.
     */
    private function updateModelAudioUrl(TtsAudioInterface&Model $model, string $attribute, string $locale, string $path): void
    {
        if (method_exists($model, 'setTranslation')) {
            $model->setTranslation($attribute, $locale, $path);
            $model->save();
        } else {
            $model->{$attribute} = $path;
            $model->save();
        }
    }

    /**
     * Validate text content.
     *
     * @phpstan-assert-if-true string $text
     */
    private function validateText(?string $text, TtsAudioInterface&Model $model, string $attribute, string $locale): bool
    {
        if (! $text) {
            Log::warning('No text content found for TTS generation', [
                'model' => get_class($model),
                'id' => $model->getKey(),
                'attribute' => $attribute,
                'locale' => $locale,
            ]);

            return false;
        }

        return true;
    }
}
