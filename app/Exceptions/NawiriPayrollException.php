<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class NawiriPayrollException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $outcomeUnknown = false,
        public readonly ?int $statusCode = null,
        ?Throwable $previous = null,
        public readonly ?string $field = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
