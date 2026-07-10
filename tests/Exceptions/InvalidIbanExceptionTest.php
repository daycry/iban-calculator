<?php

declare(strict_types=1);

namespace Tests\Exceptions;

use Daycry\Iban\DTO\ValidationResult;
use Daycry\Iban\DTO\Violation;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Exceptions\IbanException;
use Daycry\Iban\Exceptions\InvalidIbanException;
use PHPUnit\Framework\TestCase;

final class InvalidIbanExceptionTest extends TestCase
{
    public function testInvalidIbanExceptionIsInstanceOfIbanException(): void
    {
        $violation = new Violation(ViolationCode::Blank, 'iban.blank', 'IBAN cannot be blank');
        $result = new ValidationResult(false, [$violation]);
        $exception = new InvalidIbanException($result);

        self::assertInstanceOf(IbanException::class, $exception);
    }

    public function testInvalidIbanExceptionIsInstanceOfRuntimeException(): void
    {
        $violation = new Violation(ViolationCode::Blank, 'iban.blank', 'IBAN cannot be blank');
        $result = new ValidationResult(false, [$violation]);
        $exception = new InvalidIbanException($result);

        self::assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testResultMethodReturnsInjectedValidationResult(): void
    {
        $violation = new Violation(ViolationCode::Blank, 'iban.blank', 'IBAN cannot be blank');
        $result = new ValidationResult(false, [$violation]);
        $exception = new InvalidIbanException($result);

        self::assertSame($result, $exception->result());
    }

    public function testDefaultMessageUsesFirstViolationMessage(): void
    {
        $violation = new Violation(ViolationCode::Blank, 'iban.blank', 'IBAN cannot be blank');
        $result = new ValidationResult(false, [$violation]);
        $exception = new InvalidIbanException($result);

        self::assertSame('IBAN cannot be blank', $exception->getMessage());
    }

    public function testExplicitMessageOverridesDefaultMessage(): void
    {
        $violation = new Violation(ViolationCode::Blank, 'iban.blank', 'IBAN cannot be blank');
        $result = new ValidationResult(false, [$violation]);
        $exception = new InvalidIbanException($result, 'Custom message');

        self::assertSame('Custom message', $exception->getMessage());
    }

    public function testDefaultMessageWhenNoViolations(): void
    {
        $result = new ValidationResult(false, []);
        $exception = new InvalidIbanException($result);

        self::assertSame('Invalid IBAN', $exception->getMessage());
    }

    public function testExceptionCanBeThrown(): void
    {
        $violation = new Violation(ViolationCode::Blank, 'iban.blank', 'IBAN cannot be blank');
        $result = new ValidationResult(false, [$violation]);

        $this->expectException(InvalidIbanException::class);
        throw new InvalidIbanException($result);
    }
}
