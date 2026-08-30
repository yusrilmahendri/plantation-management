<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class FinanceIntegrationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?string $codeName = null,
        public readonly bool $retryable = true,
        public readonly bool $alreadyProcessed = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
