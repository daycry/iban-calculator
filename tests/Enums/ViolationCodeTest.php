<?php

declare(strict_types=1);

namespace Tests\Enums;

use Daycry\Iban\Enums\IbanFormat;
use Daycry\Iban\Enums\ViolationCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §4
 */
final class ViolationCodeTest extends TestCase
{
    public function testHasExactlySixteenCases(): void
    {
        self::assertCount(16, ViolationCode::cases());
    }

    public function testTheOriginalEightIbanCasesAreUnchanged(): void
    {
        // The BIC additions must not disturb the original IBAN codes' values.
        self::assertSame('blank', ViolationCode::Blank->value);
        self::assertSame('too_short', ViolationCode::TooShort->value);
        self::assertSame('unknown_country', ViolationCode::UnknownCountry->value);
        self::assertSame('illegal_characters', ViolationCode::IllegalCharacters->value);
        self::assertSame('bad_length', ViolationCode::BadLength->value);
        self::assertSame('malformed_structure', ViolationCode::MalformedStructure->value);
        self::assertSame('checksum_failed', ViolationCode::ChecksumFailed->value);
        self::assertSame('national_check_failed', ViolationCode::NationalCheckFailed->value);
    }

    /**
     * @return iterable<string, array{0: ViolationCode, 1: string}>
     */
    public static function violationCodeProvider(): iterable
    {
        yield 'Blank' => [ViolationCode::Blank, 'blank'];
        yield 'TooShort' => [ViolationCode::TooShort, 'too_short'];
        yield 'UnknownCountry' => [ViolationCode::UnknownCountry, 'unknown_country'];
        yield 'IllegalCharacters' => [ViolationCode::IllegalCharacters, 'illegal_characters'];
        yield 'BadLength' => [ViolationCode::BadLength, 'bad_length'];
        yield 'MalformedStructure' => [ViolationCode::MalformedStructure, 'malformed_structure'];
        yield 'ChecksumFailed' => [ViolationCode::ChecksumFailed, 'checksum_failed'];
        yield 'NationalCheckFailed' => [ViolationCode::NationalCheckFailed, 'national_check_failed'];
        yield 'BicBlank' => [ViolationCode::BicBlank, 'bic_blank'];
        yield 'BicBadLength' => [ViolationCode::BicBadLength, 'bic_bad_length'];
        yield 'BicIllegalCharacters' => [ViolationCode::BicIllegalCharacters, 'bic_illegal_characters'];
        yield 'BicMalformedStructure' => [ViolationCode::BicMalformedStructure, 'bic_malformed_structure'];
        yield 'BicUnknownCountry' => [ViolationCode::BicUnknownCountry, 'bic_unknown_country'];
        yield 'BicIbanCountryMismatch' => [ViolationCode::BicIbanCountryMismatch, 'bic_iban_country_mismatch'];
        yield 'BicIbanBankMismatch' => [ViolationCode::BicIbanBankMismatch, 'bic_iban_bank_mismatch'];
        yield 'NothingToValidate' => [ViolationCode::NothingToValidate, 'nothing_to_validate'];
    }

    #[DataProvider('violationCodeProvider')]
    public function testCaseHasExpectedValue(ViolationCode $case, string $expectedValue): void
    {
        self::assertSame($expectedValue, $case->value);
    }

    #[DataProvider('violationCodeProvider')]
    public function testFromReturnsExpectedCase(ViolationCode $case, string $expectedValue): void
    {
        self::assertSame($case, ViolationCode::from($expectedValue));
    }

    public function testFromRejectsUnknownValue(): void
    {
        $this->expectException(\ValueError::class);

        ViolationCode::from('not_a_real_violation');
    }

    public function testIbanFormatHasExactlyThreeCases(): void
    {
        self::assertCount(3, IbanFormat::cases());
    }

    public function testIbanFormatCases(): void
    {
        self::assertSame(
            ['Electronic', 'Print', 'Anonymized'],
            array_map(static fn (IbanFormat $case): string => $case->name, IbanFormat::cases())
        );
    }
}
