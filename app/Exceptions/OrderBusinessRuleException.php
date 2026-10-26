<?php

namespace App\Exceptions;

use RuntimeException;

class OrderBusinessRuleException extends RuntimeException
{
    /**
     * Campo o clave del error asociado.
     */
    protected string $errorKey;

    public function __construct(string $message, string $errorKey = 'order', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errorKey = $errorKey;
    }

    public function getErrorKey(): string
    {
        return $this->errorKey;
    }
}
