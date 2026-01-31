<?php

namespace App\Exceptions\Translation;

use Exception;

class InsufficientFundsException extends Exception
{
    public function __construct()
    {
        $message = __('exceptions.translation.insufficient_funds');
        parent::__construct($message, 402);
    }
}
