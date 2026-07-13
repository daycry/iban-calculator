<?php

declare(strict_types=1);

namespace Tests;

use Daycry\Iban\Core\BicParser;
use Daycry\Iban\Core\BicValidator;
use Daycry\Iban\DTO\ValidationResult;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Exceptions\InvalidBicException;
use Daycry\Iban\Iban;
use PHPUnit\Framework\TestCase;

/**
 * Facade-level coverage of the BIC API and the combined `validateIbanAndBic`
 * entry point. Uses the default-constructed facade — proving the whole BIC
 * stack works standalone with no CodeIgniter, no config and no database.
 */
final class IbanBicFacadeTest extends TestCase
{
    private Iban $iban;

    protected function setUp(): void
    {
        $this->iban = new Iban();
    }

    /**
     * @return list<string>
     */
    private static function codes(ValidationResult $result): array
    {
        return array_values(array_map(static fn ($v): string => $v->code->value, $result->violations()));
    }

    // -- single-BIC API -------------------------------------------------------

    public function testIsValidBicTrueForRealBics(): void
    {
        self::assertTrue($this->iban->isValidBic('CAIXESBBXXX'));
        self::assertTrue($this->iban->isValidBic('CHASUS33'));
    }

    public function testIsValidBicFalseForGarbage(): void
    {
        self::assertFalse($this->iban->isValidBic('nonsense'));
    }

    public function testValidateBicReturnsValidationResult(): void
    {
        self::assertTrue($this->iban->validateBic('CHASUS33')->isValid());
    }

    public function testNormalizeBic(): void
    {
        self::assertSame('CAIXESBBXXX', $this->iban->normalizeBic(' caix es bb xxx '));
    }

    public function testParseBicReturnsParsedBic(): void
    {
        $parsed = $this->iban->parseBic('DEUTDEFF500');

        self::assertSame('DEUT', $parsed->institutionCode);
        self::assertSame('500', $parsed->branchCode);
    }

    public function testParseBicThrowsOnInvalid(): void
    {
        $this->expectException(InvalidBicException::class);
        $this->iban->parseBic('nope');
    }

    public function testTryParseBicReturnsNullOnInvalid(): void
    {
        self::assertNull($this->iban->tryParseBic('nope'));
    }

    public function testBicSubServiceAccessors(): void
    {
        self::assertInstanceOf(BicValidator::class, $this->iban->bicValidator());
        self::assertInstanceOf(BicParser::class, $this->iban->bicParser());
    }

    public function testUsBicValidatesStandaloneWithNoDatabase(): void
    {
        // The default facade uses the bundled ISO list — a US BIC (no IBAN
        // country) must validate with zero configuration.
        self::assertTrue($this->iban->isValidBic('CHASUS33'));
    }

    // -- validateIbanAndBic: the full matrix ----------------------------------

    public function testNeitherProvidedYieldsNothingToValidate(): void
    {
        $result = $this->iban->validateIbanAndBic(null, null);

        self::assertFalse($result->isValid());
        self::assertSame([ViolationCode::NothingToValidate->value], self::codes($result));
    }

    public function testBlankStringsAlsoYieldNothingToValidate(): void
    {
        $result = $this->iban->validateIbanAndBic('   ', '');

        self::assertFalse($result->isValid());
        self::assertSame([ViolationCode::NothingToValidate->value], self::codes($result));
    }

    public function testIbanOnlyValid(): void
    {
        $result = $this->iban->validateIbanAndBic('ES9121000418450200051332', null);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    public function testIbanOnlyInvalidReturnsIbanViolation(): void
    {
        $result = $this->iban->validateIbanAndBic('ES0021000418450200051332', null);

        self::assertFalse($result->isValid());
        self::assertSame([ViolationCode::ChecksumFailed->value], self::codes($result));
    }

    public function testBicOnlyValid(): void
    {
        $result = $this->iban->validateIbanAndBic(null, 'CAIXESBBXXX');

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    public function testBicOnlyInvalidReturnsBicViolation(): void
    {
        $result = $this->iban->validateIbanAndBic(null, 'ZZ');

        self::assertFalse($result->isValid());
        self::assertSame([ViolationCode::BicBadLength->value], self::codes($result));
    }

    public function testBothProvidedAndCoherent(): void
    {
        // ES IBAN (numeric bank) + ES BIC: country matches, no bank check.
        $result = $this->iban->validateIbanAndBic('ES9121000418450200051332', 'CAIXESBB');

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    public function testBothProvidedWithCountryMismatch(): void
    {
        $result = $this->iban->validateIbanAndBic('ES9121000418450200051332', 'DEUTDEFF');

        self::assertFalse($result->isValid());
        self::assertSame([ViolationCode::BicIbanCountryMismatch->value], self::codes($result));
    }

    public function testBothProvidedWithBankMismatchForFourAlphaCountry(): void
    {
        // GB IBAN bank NWBK + GB BIC institution BARC: same country, bank differs.
        $result = $this->iban->validateIbanAndBic('GB29NWBK60161331926819', 'BARCGB22');

        self::assertFalse($result->isValid());
        self::assertSame([ViolationCode::BicIbanBankMismatch->value], self::codes($result));
    }

    public function testInvalidIbanSkipsCrossCheck(): void
    {
        // IBAN is invalid (bad checksum) and the BIC is a valid DE BIC that
        // WOULD country-mismatch an ES IBAN. Because the IBAN is invalid, the
        // cross-check must be skipped: only the IBAN violation is returned,
        // never a manufactured mismatch.
        $result = $this->iban->validateIbanAndBic('ES0021000418450200051332', 'DEUTDEFF');

        self::assertFalse($result->isValid());
        self::assertSame([ViolationCode::ChecksumFailed->value], self::codes($result));
        self::assertNotContains(ViolationCode::BicIbanCountryMismatch->value, self::codes($result));
    }

    public function testInvalidBicSkipsCrossCheck(): void
    {
        // Valid ES IBAN + malformed BIC: only the BIC violation, no cross-check.
        $result = $this->iban->validateIbanAndBic('ES9121000418450200051332', 'BADBIC');

        self::assertFalse($result->isValid());
        self::assertNotContains(ViolationCode::BicIbanCountryMismatch->value, self::codes($result));
        self::assertNotContains(ViolationCode::BicIbanBankMismatch->value, self::codes($result));
    }
}
