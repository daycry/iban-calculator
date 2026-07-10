<?php

declare(strict_types=1);

namespace Tests\DTO;

use Daycry\Iban\DTO\ValidationResult;
use Daycry\Iban\DTO\Violation;
use Daycry\Iban\Enums\ViolationCode;
use PHPUnit\Framework\TestCase;

final class ValidationResultTest extends TestCase
{
    public function testValidResultReturnsTrue(): void
    {
        $result = new ValidationResult(true, []);

        self::assertTrue($result->isValid());
    }

    public function testValidResultHasNoViolations(): void
    {
        $result = new ValidationResult(true, []);

        self::assertSame([], $result->violations());
    }

    public function testValidResultFirstViolationIsNull(): void
    {
        $result = new ValidationResult(true, []);

        self::assertNull($result->firstViolation());
    }

    public function testInvalidResultReturnsFalse(): void
    {
        $violation1 = new Violation(ViolationCode::Blank, 'iban.blank', 'IBAN cannot be blank');
        $violation2 = new Violation(ViolationCode::TooShort, 'iban.too_short', 'IBAN is too short');
        $result = new ValidationResult(false, [$violation1, $violation2]);

        self::assertFalse($result->isValid());
    }

    public function testInvalidResultReturnsViolations(): void
    {
        $violation1 = new Violation(ViolationCode::Blank, 'iban.blank', 'IBAN cannot be blank');
        $violation2 = new Violation(ViolationCode::TooShort, 'iban.too_short', 'IBAN is too short');
        $result = new ValidationResult(false, [$violation1, $violation2]);

        $violations = $result->violations();
        self::assertCount(2, $violations);
        self::assertSame($violation1, $violations[0]);
        self::assertSame($violation2, $violations[1]);
    }

    public function testInvalidResultFirstViolationReturnsFirst(): void
    {
        $violation1 = new Violation(ViolationCode::Blank, 'iban.blank', 'IBAN cannot be blank');
        $violation2 = new Violation(ViolationCode::TooShort, 'iban.too_short', 'IBAN is too short');
        $result = new ValidationResult(false, [$violation1, $violation2]);

        self::assertSame($violation1, $result->firstViolation());
    }

    public function testInvalidResultSingleViolation(): void
    {
        $violation = new Violation(ViolationCode::ChecksumFailed, 'iban.checksum_failed', 'Checksum validation failed');
        $result = new ValidationResult(false, [$violation]);

        self::assertFalse($result->isValid());
        self::assertSame($violation, $result->firstViolation());
    }
}
