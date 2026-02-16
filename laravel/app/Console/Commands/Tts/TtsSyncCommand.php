<?php

namespace App\Console\Commands\Tts;

use App\Contracts\TtsAudioInterface;
use App\Enums\Tts\TtsModelMappingEnum;
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
        $locales = config('app.available_locales');
        $count = 0;

        foreach (TtsModelMappingEnum::cases() as $mapping) {
            /** @var class-string<Model> $modelClass */
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

            foreach ($attributesToTts as $textAttr => $audioAttr) {
                foreach ($locales as $locale) {
                    $count += $this->syncGroup($modelClass, $textAttr, $audioAttr, $locale);
                }
            }
        }

        $this->info("TTS synchronization scan completed. Dispatched {$count} jobs.");
        Log::channel('tts')->info('TTS synchronization scan completed', ['dispatched_count' => $count]);

        return Command::SUCCESS;
    }

    private function syncGroup(string $modelClass, string $textAttr, string $audioAttr, string $locale): int
    {
        $dispatched = 0;

        /** @var \Illuminate\Support\Collection<int, Model&TtsAudioInterface> $records */
        $records = $modelClass::all()->filter(function (Model $model) use ($audioAttr, $locale) {
            /** @var Model&TtsAudioInterface $model */
            if (! method_exists($model, 'getTranslation')) {
                return false;
            }

            $audio = $model->getTranslation($audioAttr, $locale, false);

            return empty($audio);
        });

        if ($records->isEmpty()) {
            return 0;
        }

        foreach ($records as $model) {
            $text = $model->getTtsText($audioAttr, $locale);

            if (! $text) {
                continue;
            }

            GenerateTtsAudioJob::dispatch(
                TtsAudioContext::make($model, $audioAttr, $locale, $text)
            );
            $dispatched++;
        }

        if ($dispatched > 0) {
            $this->comment("  - Dispatched {$dispatched} jobs for {$textAttr} [{$locale}]");
        }

        return $dispatched;
    }
}
