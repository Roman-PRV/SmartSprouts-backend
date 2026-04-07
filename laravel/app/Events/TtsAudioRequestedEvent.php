<?php

namespace App\Events;

use App\Services\Tts\DTO\TtsAudioContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TtsAudioRequestedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  TtsAudioContext  $context  The TTS audio context
     */
    public function __construct(
        public readonly TtsAudioContext $context,
    ) {}
}
