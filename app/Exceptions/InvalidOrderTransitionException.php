<?php

namespace App\Exceptions;

class InvalidOrderTransitionException extends OrderBusinessRuleException
{
    public function __construct(string $message = 'La transición de estado solicitada no es válida.', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, 'status', $code, $previous);
    }
}
