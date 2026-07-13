<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\BicValidator;
use Daycry\Iban\DTO\ParsedBic;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Registry\IsoCountryRegistry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the ISO 9362 BIC well-formedness rules end to end.
 */
final class BicValidatorTest extends TestCase
{
    private BicValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new BicValidator(new IsoCountryRegistry());
    }

    // -- valid BICs -----------------------------------------------------------

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validBicProvider(): iterable
    {
        yield 'BIC8 (head office)' => ['CHASUS33'];
        yield 'BIC8 Santander ES'  => ['BSCHESMM'];
        yield 'BIC11 XXX branch'    => ['CAIXESBBXXX'];
        yield 'BIC11 numeric branch' => ['DEUTDEFF500'];
        yield 'BIC8 US (no IBAN country)' => ['CHASUS33'];
        yield 'BIC8 GB' => ['BARCGB22'];
        yield 'BIC11 with XXX GB' => ['BARCGB22XXX'];
        yield 'digit 2 at location char 1' => ['DEUTDE2H'];
        yield 'digit at location char 2 (passive)' => ['DEUTDEF1'];
    }

    #[DataProvider('validBicProvider')]
    public function testValidBicsPass(string $bic): void
    {
        self::assertTrue($this->validator->isValid($bic), $bic . ' should be a valid BIC');
    }

    public function testUsBicFromCountryWithNoIbanValidates(): void
    {
        // The whole reason for the full ISO 3166-1 list: the US issues no IBAN,
        // so the ~78-country IBAN registry does not know it, yet real US BICs
        // must validate.
        self::assertTrue($this->validator->isValid('CHASUS33'));
    }

    // -- normalization --------------------------------------------------------

    public function testLowercaseInputIsNormalizedAndValid(): void
    {
        self::assertTrue($this->validator->isValid('caixesbbxxx'));
    }

    public function testSpacedInputIsNormalizedAndValid(): void
    {
        self::assertTrue($this->validator->isValid(' caix es bb xxx '));
    }

    public function testNormalizeStripsWhitespaceAndUppercases(): void
    {
        self::assertSame('CAIXESBBXXX', $this->validator->normalize(' caix es bb xxx '));
    }

    // -- rejection paths ------------------------------------------------------

    public function testBlankIsRejected(): void
    {
        $result = $this->validator->validate('   ');

        self::assertFalse($result->isValid());
        self::assertSame(ViolationCode::BicBlank, $result->firstViolation()?->code);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function badLengthProvider(): iterable
    {
        yield '7 chars'  => ['CHASUS3'];
        yield '9 chars'  => ['CHASUS33X'];
        yield '10 chars' => ['CHASUS33XX'];
        yield '12 chars (SWIFTNet LT address, not an ISO 9362 BIC)' => ['CHASUS33XXXX'];
    }

    #[DataProvider('badLengthProvider')]
    public function testBadLengthIsRejected(string $bic): void
    {
        $result = $this->validator->validate($bic);

        self::assertFalse($result->isValid());
        self::assertSame(ViolationCode::BicBadLength, $result->firstViolation()?->code);
    }

    public function testIllegalCharactersAreRejected(): void
    {
        // Right length (8), but contains a hyphen (whitespace-strip keeps it).
        $result = $this->validator->validate('CHAS-U33');

        self::assertFalse($result->isValid());
        self::assertSame(ViolationCode::BicIllegalCharacters, $result->firstViolation()?->code);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function malformedStructureProvider(): iterable
    {
        yield 'digits in institution code'  => ['1234US33'];
        yield 'digit in country code'        => ['CHASU133'];
        yield 'digit 0 at location char 1'   => ['CHASUS03'];
        yield 'digit 1 at location char 1'   => ['CHASUS13'];
        yield 'letter O at location char 2'  => ['CHASUS3O'];
    }

    #[DataProvider('malformedStructureProvider')]
    public function testMalformedStructureIsRejected(string $bic): void
    {
        $result = $this->validator->validate($bic);

        self::assertFalse($result->isValid());
        self::assertSame(ViolationCode::BicMalformedStructure, $result->firstViolation()?->code);
    }

    public function testUnknownCountryIsRejected(): void
    {
        // 'ZZ' is a user-assigned code, never officially assigned; well-formed
        // otherwise, so the structure passes and only the country fails.
        $result = $this->validator->validate('CHASZZ33');

        self::assertFalse($result->isValid());
        self::assertSame(ViolationCode::BicUnknownCountry, $result->firstViolation()?->code);
    }

    public function testKosovoXkCountryCodeIsAccepted(): void
    {
        // XK is user-assigned (not officially ISO), deliberately excluded from
        // the ISO registry, but used in real BICs — the validator layers it on.
        self::assertTrue($this->validator->isValid('NLBBXKPR'));
    }

    // -- ParsedBic passthrough ------------------------------------------------

    public function testAlreadyParsedBicRevalidatesWithoutReNormalizing(): void
    {
        $parsed = new ParsedBic('CHASUS33', 'CHAS', 'US', '33', null);

        self::assertTrue($this->validator->isValid($parsed));
    }

    // -- toParsedBic ----------------------------------------------------------

    public function testToParsedBicSlicesBic8(): void
    {
        $parsed = $this->validator->toParsedBic('CHASUS33');

        self::assertSame('CHAS', $parsed->institutionCode);
        self::assertSame('US', $parsed->countryCode);
        self::assertSame('33', $parsed->locationCode);
        self::assertNull($parsed->branchCode);
    }

    public function testToParsedBicSlicesBic11(): void
    {
        $parsed = $this->validator->toParsedBic('DEUTDEFF500');

        self::assertSame('DEUT', $parsed->institutionCode);
        self::assertSame('DE', $parsed->countryCode);
        self::assertSame('FF', $parsed->locationCode);
        self::assertSame('500', $parsed->branchCode);
    }
}
