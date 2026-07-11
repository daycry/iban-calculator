<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\Validator;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Registry\CountryStructure;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\IbanFixtures;

/**
 * Per-country validation coverage (T-45): runs the full {@see Validator}
 * pipeline (default wiring, same as {@see \Daycry\Iban\Config\Services::iban()})
 * against every one of the 78 registered countries, so validation behaviour
 * is exercised for each country individually rather than only through a
 * handful of hand-picked examples elsewhere in the suite.
 *
 * Parametrized from {@see Registry::all()} (like
 * {@see \Tests\Registry\CountriesDataIntegrityTest}), so it automatically
 * grows as new countries are added to the registry. Per country:
 *   - the registry's own example IBAN validates true;
 *   - a second, independently-generated valid IBAN (see
 *     {@see IbanFixtures::secondValid()}) also validates true, and is
 *     genuinely distinct from the example;
 *   - a same-length variant with broken check digits validates false with
 *     {@see ViolationCode::ChecksumFailed};
 *   - a one-char-short variant validates false with
 *     {@see ViolationCode::BadLength}.
 *
 * @see .superpowers/sdd/task-45-50-brief.md
 */
final class CountryValidationTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator(new Registry());
    }

    /**
     * @return iterable<string, array{0: CountryStructure}>
     */
    public static function countryProvider(): iterable
    {
        foreach ((new Registry())->all() as $countryCode => $structure) {
            yield $countryCode => [$structure];
        }
    }

    #[DataProvider('countryProvider')]
    public function testRegistryExampleValidates(CountryStructure $country): void
    {
        self::assertTrue(
            $this->validator->isValid($country->ibanExampleElectronic),
            sprintf('%s: registry example "%s" should validate.', $country->countryCode, $country->ibanExampleElectronic),
        );
    }

    #[DataProvider('countryProvider')]
    public function testSecondGeneratedIbanValidatesAndDiffersFromExample(CountryStructure $country): void
    {
        $second = IbanFixtures::secondValid($country);

        self::assertTrue(
            $this->validator->isValid($second),
            sprintf('%s: generated second IBAN "%s" should validate.', $country->countryCode, $second),
        );
        self::assertNotSame(
            $country->ibanExampleElectronic,
            $second,
            sprintf('%s: generated second IBAN should differ from the registry example.', $country->countryCode),
        );
    }

    #[DataProvider('countryProvider')]
    public function testBadChecksumVariantFailsWithChecksumFailed(CountryStructure $country): void
    {
        $bad    = IbanFixtures::badChecksum($country->ibanExampleElectronic);
        $result = $this->validator->validate($bad);

        self::assertFalse($result->isValid(), sprintf('%s: "%s" should be invalid.', $country->countryCode, $bad));
        self::assertSame(
            ViolationCode::ChecksumFailed,
            $result->firstViolation()?->code,
            sprintf('%s: "%s" should fail with ChecksumFailed.', $country->countryCode, $bad),
        );
    }

    #[DataProvider('countryProvider')]
    public function testBadLengthVariantFailsWithBadLength(CountryStructure $country): void
    {
        $bad    = IbanFixtures::badLength($country->ibanExampleElectronic);
        $result = $this->validator->validate($bad);

        self::assertFalse($result->isValid(), sprintf('%s: "%s" should be invalid.', $country->countryCode, $bad));
        self::assertSame(
            ViolationCode::BadLength,
            $result->firstViolation()?->code,
            sprintf('%s: "%s" should fail with BadLength.', $country->countryCode, $bad),
        );
    }
}
