<?php

namespace App\Listeners;

use App\Events\TtsAudioRequestedEvent;
use App\Jobs\Tts\GenerateTtsAudioJob;
use App\Services\Tts\DTO\TtsAudioContext;
use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;

class GenerateMissingAudioListener
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * The number of seconds before a lock expires.
     */
    private const LOCK_DURATION = 300; // 5 minutes

    public function handle(TtsAudioRequestedEvent $event): void
    {
        try {
            $lockKey = $this->getLockKey($event->context);
            $lock = Cache::lock($lockKey, self::LOCK_DURATION);
            if ($lock->get()) {
                try {
                    $this->processGeneration($event->context);
                } finally {
                    $lock->release();
                }
            } else {
                $this->logLockFailure($lockKey);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Failed to handle TTS audio generation', [
                ...$event->context->toLogContext(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
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
     * Process the audio generation request.
     */
    private function processGeneration(TtsAudioContext $context): bool
    {
        $model = $context->getModel()->fresh();

        if (! $model) {
            return false;
        }

        $currentValue = $model->getTranslatableAttribute($context->getAttribute(), $context->getLocale());

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
        $this->logger->info('Audio already exists, skipping generation', $context->toLogContext());
    }

    private function logJobDispatched(TtsAudioContext $context): void
    {
        $this->logger->info('Dispatched TTS generation job', $context->toLogContext());
    }

    private function logLockFailure(string $lockKey): void
    {
        $this->logger->debug('Could not acquire lock for TTS generation, skipping', [
            'lock_key' => $lockKey,
        ]);
    }
}
