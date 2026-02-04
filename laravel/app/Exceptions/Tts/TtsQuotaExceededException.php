<?php

namespace App\Exceptions\Tts;

use Exception;

class TtsQuotaExceededException extends Exception
{
    public function __construct(
        string $message = 'TTS provider quota exceeded',
        int $code = 429,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
