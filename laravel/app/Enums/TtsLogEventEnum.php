<?php

namespace App\Enums;

enum TtsLogEventEnum: string
{
    case SYNTHESIS_STARTED = 'tts.synthesis_started';
    case SYNTHESIS_SUCCESS = 'tts.synthesis_success';
    case SYNTHESIS_FAILED = 'tts.synthesis_failed';
    case PROVIDER_QUOTA_EXCEEDED = 'tts.provider_quota_exceeded';
    case VOICES_FETCH_FAILED = 'tts.voices_fetch_failed';
}
