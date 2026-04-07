<?php

namespace App\Exceptions\Tts;

use Exception;

class InvalidVoiceException extends Exception
{
    public function __construct(
        ?string $message = null,
        int $code = 400,
        ?\Throwable $previous = null,
    ) {
        $message ??= __('exceptions.tts.invalid_voice');
        parent::__construct($message, $code, $previous);
    }
}
