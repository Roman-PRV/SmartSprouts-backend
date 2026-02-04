<?php

namespace App\Exceptions\Tts;

use Exception;

class TtsFailedException extends Exception
{
    public function __construct(
        string $message = 'Text-to-speech synthesis failed',
        int $code = 500,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
