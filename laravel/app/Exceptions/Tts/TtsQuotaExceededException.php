<?php

namespace App\Exceptions\Tts;

use Exception;

class TtsQuotaExceededException extends Exception
{
    public function __construct(
        ?string $message = null,
        int $code = 429,
        ?\Throwable $previous = null,
    ) {
        $message ??= __('exceptions.tts.quota_exceeded');
        parent::__construct($message, $code, $previous);
    }
}
