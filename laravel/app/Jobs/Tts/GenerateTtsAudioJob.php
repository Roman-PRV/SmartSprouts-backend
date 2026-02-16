<?php

namespace App\Jobs\Tts;

use App\Enums\TtsLogEventEnum;
use App\Exceptions\Tts\TtsQuotaExceededException;
use App\Helpers\ConfigHelper;
use App\Services\Tts\DTO\TtsAudioContext;
use App\Services\Tts\TtsAudioGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

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
     * Create a new job instance.
     *
     * @param  TtsAudioContext  $context  The TTS audio context
     */
    public function __construct(
        public readonly TtsAudioContext $context,
    ) {
        $this->onQueue(ConfigHelper::getString('ai.tts.auto_generate.queue', 'tts'));
    }

    /**
     * Execute the job.
     */
    public function handle(TtsAudioGeneratorService $audioGenerator): void
    {
        try {
            $model = $this->context->getModel();

            Log::info('Starting TTS audio generation via Job', $this->context->toLogContext());

            $audioGenerator->generateForModel($this->context);

        } catch (TtsQuotaExceededException $e) {
            Log::error(TtsLogEventEnum::PROVIDER_QUOTA_EXCEEDED->value, [
                ...$this->context->toLogContext(),
                'error' => $e->getMessage(),
            ]);

            // Release job back to queue with exponential backoff
            $this->release($this->calculateBackoff());
        } catch (\Throwable $e) {
            Log::error(TtsLogEventEnum::SYNTHESIS_FAILED->value, [
                ...$this->context->toLogContext(),
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
