<?php

namespace App\Exceptions;

use Exception;

class AIServiceException extends Exception
{
    public function __construct(string $message, protected int $statusCode = 502)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
