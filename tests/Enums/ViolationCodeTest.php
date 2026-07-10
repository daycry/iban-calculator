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
    public function testHasExactlyEightCases(): void
    {
        self::assertCount(8, ViolationCode::cases());
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
