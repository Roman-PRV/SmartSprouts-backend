<?php

namespace App\Jobs\Tts;

use App\Contracts\TtsAudioInterface;
use App\Enums\TtsLogEventEnum;
use App\Exceptions\Tts\TtsQuotaExceededException;
use App\Helpers\ConfigHelper;
use App\Services\Tts\DTO\TtsAudioContext;
use App\Services\Tts\TtsAudioGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Psr\Log\LoggerInterface;

class GenerateTtsAudioJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * Direct model + attribute + locale (instead of wrapping in TtsAudioContext)
     * so SerializesModels replaces $model with a lightweight ModelIdentifier
     * during queue serialization. Wrapping the model inside a DTO would bypass
     * this optimization — the entire model object would be serialized, causing
     * payload bloat and stale-data risk.
     *
     * Text is optional: lazy-path callers (listener, sync command) pass it
     * pre-resolved; observer path leaves it null and lets the generator
     * extract it fresh from the (re-loaded) model.
     */
    public function __construct(
        public readonly TtsAudioInterface&Model $model,
        public readonly string $attribute,
        public readonly string $locale,
        public readonly ?string $text = null,
    ) {
        $this->onQueue(ConfigHelper::getString('ai.tts.auto_generate.queue', 'tts'));
    }

    /**
     * Execute the job.
     */
    public function handle(TtsAudioGeneratorService $audioGenerator, LoggerInterface $logger): void
    {
        $context = TtsAudioContext::make($this->model, $this->attribute, $this->locale, $this->text);

        try {
            $logger->info('Starting TTS audio generation via Job', $context->toLogContext());

            $audioGenerator->generateForModel($context);

        } catch (TtsQuotaExceededException $e) {
            $logger->error(TtsLogEventEnum::PROVIDER_QUOTA_EXCEEDED->value, [
                ...$context->toLogContext(),
                'error' => $e->getMessage(),
            ]);

            // Release job back to queue with exponential backoff
            $this->release($this->calculateBackoff());
        } catch (\Throwable $e) {
            $logger->error(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
                ...$context->toLogContext(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate exponential backoff delay.
     */
    private function calculateBackoff(): int
    {
        return $this->backoff * ($this->attempts() ** 2);
    }
}
