<?php

namespace App\Facades;

use App\Services\Tts\TtsOrchestrator;
use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null getOrGenerate(\App\Services\Tts\DTO\TtsAudioContext $context)
 *
 * @see \App\Services\Tts\TtsOrchestrator
 */
class Tts extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return TtsOrchestrator::class;
    }
}
