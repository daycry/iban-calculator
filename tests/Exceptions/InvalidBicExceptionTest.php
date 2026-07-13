<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use Daycry\Iban\DTO\ValidationResult;
use Daycry\Iban\DTO\Violation;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Exceptions\IbanException;
use Daycry\Iban\Exceptions\InvalidBicException;
use PHPUnit\Framework\TestCase;

final class InvalidBicExceptionTest extends TestCase
{
    public function testIsInstanceOfIbanException(): void
    {
        $result    = new ValidationResult(false, [new Violation(ViolationCode::BicBlank, 'bic.violation.blank', 'The BIC is empty.')]);
        $exception = new InvalidBicException($result);

        self::assertInstanceOf(IbanException::class, $exception);
    }

    public function testIsInstanceOfRuntimeException(): void
    {
        $result    = new ValidationResult(false, [new Violation(ViolationCode::BicBlank, 'bic.violation.blank', 'The BIC is empty.')]);
        $exception = new InvalidBicException($result);

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testResultMethodReturnsInjectedValidationResult(): void
    {
        $result    = new ValidationResult(false, [new Violation(ViolationCode::BicBlank, 'bic.violation.blank', 'The BIC is empty.')]);
        $exception = new InvalidBicException($result);

        self::assertSame($result, $exception->result());
    }

    public function testDefaultMessageUsesFirstViolationMessage(): void
    {
        $result    = new ValidationResult(false, [new Violation(ViolationCode::BicBadLength, 'bic.violation.bad_length', 'The BIC must be 8 or 11 characters long.')]);
        $exception = new InvalidBicException($result);

        self::assertSame('The BIC must be 8 or 11 characters long.', $exception->getMessage());
    }

    public function testExplicitMessageOverridesDefaultMessage(): void
    {
        $result    = new ValidationResult(false, [new Violation(ViolationCode::BicBlank, 'bic.violation.blank', 'The BIC is empty.')]);
        $exception = new InvalidBicException($result, 'Custom message');

        self::assertSame('Custom message', $exception->getMessage());
    }

    public function testDefaultMessageWhenNoViolations(): void
    {
        $exception = new InvalidBicException(new ValidationResult(false, []));

        self::assertSame('Invalid BIC', $exception->getMessage());
    }
}
