<?php

namespace App\Listeners;

use App\Events\TtsAudioRequestedEvent;
use App\Jobs\Tts\GenerateTtsAudioJob;
use App\Services\Tts\DTO\TtsAudioContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateMissingAudioListener
{
    /**
     * The number of seconds before a lock expires.
     */
    private const LOCK_DURATION = 300; // 5 minutes

    public function handle(TtsAudioRequestedEvent $event): void
    {
        $context = $event->context;
        $lockKey = $this->getLockKey($context);

        $acquired = Cache::lock($lockKey, self::LOCK_DURATION)
            ->get(fn () => $this->processGeneration($context));

        if (! $acquired) {
            $this->logLockFailure($lockKey);
        }
    }

    /**
     * Generate a unique lock key for this audio generation request.
     */
    private function getLockKey(TtsAudioContext $context): string
    {
        $model = $context->getModel();
        $modelType = str_replace('\\', '_', get_class($model));

        /** @var int $id */
        $id = $model->getKey();

        return sprintf(
            'tts_generation:%s:%s:%s:%s',
            $modelType,
            $id,
            $context->getAttribute(),
            $context->getLocale()
        );
    }

    /**
     * Get the current audio URL value from the model.
     */
    private function getCurrentAudioUrl(TtsAudioContext $context): ?string
    {
        $model = $context->getModel();
        $model->refresh();

        return $model->getTranslatableAttribute($context->getAttribute(), $context->getLocale());
    }

    /**
     * Process the audio generation request.
     */
    private function processGeneration(TtsAudioContext $context): bool
    {
        $currentValue = $this->getCurrentAudioUrl($context);

        if ($currentValue !== null && $currentValue !== '') {
            $this->logAudioExists($context);

            return false;
        }

        GenerateTtsAudioJob::dispatch($context);

        $this->logJobDispatched($context);

        return true;
    }

    private function logAudioExists(TtsAudioContext $context): void
    {
        Log::info('Audio already exists, skipping generation', $context->toLogContext());
    }

    private function logJobDispatched(TtsAudioContext $context): void
    {
        Log::info('Dispatched TTS generation job', $context->toLogContext());
    }

    private function logLockFailure(string $lockKey): void
    {
        Log::debug('Could not acquire lock for TTS generation, skipping', [
            'lock_key' => $lockKey,
        ]);
    }
}
