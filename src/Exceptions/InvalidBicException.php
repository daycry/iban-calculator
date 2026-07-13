<?php

declare(strict_types=1);

namespace Daycry\Iban\Exceptions;

use Daycry\Iban\DTO\ValidationResult;

/**
 * Thrown by {@see \Daycry\Iban\Core\BicParser::parse()} when strict parsing is
 * asked to turn a malformed BIC into a {@see \Daycry\Iban\DTO\ParsedBic}.
 *
 * Mirrors {@see InvalidIbanException} exactly: it carries the
 * {@see ValidationResult} that caused the failure (retrievable via
 * {@see result()}), so a caller catching it can inspect the specific
 * {@see \Daycry\Iban\DTO\Violation}s rather than only reading the message.
 */
final class InvalidBicException extends IbanException
{
    public function __construct(
        private readonly ValidationResult $resultValue,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        $firstViolation = $resultValue->firstViolation();
        $defaultMessage = $firstViolation !== null ? $firstViolation->message : 'Invalid BIC';
        $finalMessage   = $message !== '' ? $message : $defaultMessage;

        parent::__construct($finalMessage, $code, $previous);
    }

    public function result(): ValidationResult
    {
        return $this->resultValue;
    }
}
