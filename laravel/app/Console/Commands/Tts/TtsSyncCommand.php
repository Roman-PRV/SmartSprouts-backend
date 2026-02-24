<?php

namespace App\Console\Commands\Tts;

use App\Contracts\TtsAudioInterface;
use App\Enums\Tts\TtsModelMappingEnum;
use App\Helpers\ConfigHelper;
use App\Jobs\Tts\GenerateTtsAudioJob;
use App\Services\Tts\DTO\TtsAudioContext;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class TtsSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tts:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan all game models for missing TTS audio and dispatch generation jobs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting TTS synchronization scan...');
        Log::channel('tts')->info('Starting TTS synchronization scan via CLI');

        /** @var array<string> $locales */
        $locales = ConfigHelper::getStringList('app.supported_locales');
        $totalCount = 0;

        foreach (TtsModelMappingEnum::cases() as $mapping) {
            /** @var class-string<Model&TtsAudioInterface> $modelClass */
            $modelClass = $mapping->value;

            // Ensure the model supports TTS
            if (! is_subclass_of($modelClass, TtsAudioInterface::class)) {
                $this->error("Model {$modelClass} does not support TTS (not a TtsAudioInterface). Skipping.");

                continue;
            }

            $this->comment("Processing model: {$modelClass}");

            // Common attributes that require TTS
            $attributesToTts = [
                'statement' => 'statement_audio_url',
                'explanation' => 'explanation_audio_url',
            ];

            $modelCount = 0;

            $modelClass::query()->lazy()->each(function (Model $record) use ($attributesToTts, $locales, &$modelCount, &$totalCount) {
                if (! $record instanceof TtsAudioInterface) {
                    return;
                }

                foreach ($attributesToTts as $textAttr => $audioAttr) {
                    foreach ($locales as $locale) {
                        if ($this->shouldDispatch($record, $audioAttr, $locale)) {
                            $text = $record->getTtsText($audioAttr, $locale);

                            if (! $text) {
                                continue;
                            }

                            GenerateTtsAudioJob::dispatch(
                                TtsAudioContext::make($record, $audioAttr, $locale, $text)
                            );

                            $modelCount++;
                            $totalCount++;
                        }
                    }
                }
            });

            if ($modelCount > 0) {
                $this->comment("  - Dispatched {$modelCount} jobs for ".class_basename($modelClass));
            }
        }

        $this->info("TTS synchronization scan completed. Dispatched {$totalCount} jobs.");
        Log::channel('tts')->info('TTS synchronization scan completed', ['dispatched_count' => $totalCount]);

        return Command::SUCCESS;
    }

    /**
     * Check if a job should be dispatched for the given record and attribute.
     */
    private function shouldDispatch(Model&TtsAudioInterface $model, string $audioAttr, string $locale): bool
    {
        if (! method_exists($model, 'getTranslation')) {
            return false;
        }

        $audio = $model->getTranslation($audioAttr, $locale, false);

        return empty($audio);
    }
}
