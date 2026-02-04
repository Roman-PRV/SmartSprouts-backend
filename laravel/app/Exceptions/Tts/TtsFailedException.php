<?php

namespace App\Exceptions\Tts;

use Exception;

class TtsFailedException extends Exception
{
    public function __construct(
        ?string $message = null,
        int $code = 500,
        ?\Throwable $previous = null,
    ) {
        $message ??= __('exceptions.tts.failed');
        parent::__construct($message, $code, $previous);
    }
}
