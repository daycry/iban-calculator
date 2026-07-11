<?php

declare(strict_types=1);

namespace Daycry\Iban\Exceptions;

use Daycry\Iban\DTO\ValidationResult;

final class InvalidIbanException extends IbanException
{
    public function __construct(
        private readonly ValidationResult $resultValue,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $firstViolation = $resultValue->firstViolation();
        $defaultMessage = $firstViolation !== null ? $firstViolation->message : 'Invalid IBAN';
        $finalMessage = $message !== '' ? $message : $defaultMessage;

        parent::__construct($finalMessage, $code, $previous);
    }

    public function result(): ValidationResult
    {
        return $this->resultValue;
    }
}
