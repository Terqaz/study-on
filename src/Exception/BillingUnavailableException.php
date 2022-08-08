<?php

namespace App\Exception;

use Exception;
use Throwable;

class BillingUnavailableException extends Exception
{
    public function __construct($message = null, $code = 0, Throwable $previous = null)
    {
        $message = $message ?? 'Сервис временно недоступен';

        parent::__construct($message, $code, $previous);
    }
}
