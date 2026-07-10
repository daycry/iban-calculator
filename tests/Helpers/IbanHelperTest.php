<?php

declare(strict_types=1);

namespace Tests\Helpers;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Iban\Core\Mod97;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\DTO\ValidationResult;

/**
 * Exercises `src/Helpers/iban_helper.php` through CI4's real helper
 * discovery (`helper('iban')`), which locates the file via the
 * `Daycry\Iban\` => `src/` PSR-4 namespace mapping — no `Registrar`
 * autoload hook required (see `src/Config/Registrar.php`).
 *
 * @see \Daycry\Iban\Config\Registrar
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class IbanHelperTest extends CIUnitTestCase
{
    private const VALID_IBAN = 'ES9121000418450200051332';

    protected function setUp(): void
    {
        parent::setUp();

        helper('iban');
    }

    public function testIbanIsValidReturnsTrueForAValidIban(): void
    {
        self::assertTrue(iban_is_valid(self::VALID_IBAN));
    }

    public function testIbanIsValidReturnsFalseForGarbage(): void
    {
        self::assertFalse(iban_is_valid('basura'));
    }

    public function testIbanValidAliasBehavesLikeIbanIsValid(): void
    {
        self::assertTrue(iban_valid(self::VALID_IBAN));
        self::assertFalse(iban_valid('basura'));
    }

    public function testIbanParseReturnsAParsedIbanForAValidIban(): void
    {
        $parsed = iban_parse(self::VALID_IBAN);

        self::assertInstanceOf(ParsedIban::class, $parsed);
        self::assertSame('ES', $parsed->countryCode);
    }

    public function testIbanParseReturnsNullForGarbageInsteadOfThrowing(): void
    {
        self::assertNull(iban_parse('basura'));
    }

    public function testIbanCountryReturnsTheCountryCodeForAValidIban(): void
    {
        self::assertSame('ES', iban_country(self::VALID_IBAN));
    }

    public function testIbanCountryReturnsNullForGarbage(): void
    {
        self::assertNull(iban_country('basura'));
    }

    public function testIbanFormatPrintGroupsInBlocksOfFour(): void
    {
        self::assertSame(
            'ES91 2100 0418 4502 0005 1332',
            iban_format(self::VALID_IBAN, 'print'),
        );
    }

    public function testIbanFormatDefaultsToPrint(): void
    {
        self::assertSame(
            'ES91 2100 0418 4502 0005 1332',
            iban_format(self::VALID_IBAN),
        );
    }

    public function testIbanFormatElectronicHasNoSpaces(): void
    {
        self::assertSame(
            self::VALID_IBAN,
            iban_format(self::VALID_IBAN, 'electronic'),
        );
    }

    public function testIbanFormatAnonymizedMasksTheMiddle(): void
    {
        self::assertSame(
            'ES******************1332',
            iban_format(self::VALID_IBAN, 'anonymized'),
        );
    }

    public function testBankNameReturnsNullWithAnEmptyDatabase(): void
    {
        self::assertNull(bank_name(self::VALID_IBAN));
    }

    public function testBankNameReturnsNullForGarbageInsteadOfThrowing(): void
    {
        self::assertNull(bank_name('basura'));
    }

    public function testBankBicReturnsNullWithAnEmptyDatabase(): void
    {
        self::assertNull(bank_bic(self::VALID_IBAN));
    }

    public function testBankBicReturnsNullForGarbageInsteadOfThrowing(): void
    {
        self::assertNull(bank_bic('basura'));
    }

    public function testIbanValidateReturnsAValidationResult(): void
    {
        $result = iban_validate(self::VALID_IBAN);

        self::assertInstanceOf(ValidationResult::class, $result);
        self::assertTrue($result->isValid());
    }

    public function testIbanValidateReturnsAnInvalidResultForGarbage(): void
    {
        $result = iban_validate('basura');

        self::assertInstanceOf(ValidationResult::class, $result);
        self::assertFalse($result->isValid());
    }

    public function testIbanValidateDefaultsCheckNationalToFalseAndIgnoresBadNationalDigits(): void
    {
        // Without the flag, the national validator is never invoked: the
        // IBAN is structurally sound and MOD-97-valid, so it is valid --
        // mirrors ValidatorNationalTest's equivalent facade-level case.
        self::assertTrue(iban_validate($this->esIbanWithBadNationalCheckDigits())->isValid());
    }

    /**
     * Fix 4 of the final v1.0 review: `iban_validate()` exposes the same
     * `$checkNational` flag as the facade's `validate()`. A MOD-97-valid ES
     * IBAN whose national (mod-11) check digits are wrong only fails when
     * `checkNational: true` is passed through.
     */
    public function testIbanValidateWithCheckNationalTrueFailsANationallyInvalidEsIban(): void
    {
        $result = iban_validate($this->esIbanWithBadNationalCheckDigits(), checkNational: true);

        self::assertFalse($result->isValid());
    }

    /**
     * Every function in the helper must be wrapped in a
     * `function_exists()` guard so re-including the file (as `helper()`
     * does whenever a consuming app also ships an `iban_helper.php`, or as
     * happens here via a direct `require`) never triggers a fatal
     * "Cannot redeclare" error.
     */
    public function testHelperFileCanBeIncludedTwiceWithoutRedeclaring(): void
    {
        require __DIR__ . '/../../src/Helpers/iban_helper.php';
        require __DIR__ . '/../../src/Helpers/iban_helper.php';

        self::assertTrue(function_exists('iban_is_valid'));
        self::assertTrue(iban_is_valid(self::VALID_IBAN));
    }

    /**
     * Builds a MOD-97-valid ES IBAN whose national (mod-11) check digits
     * are wrong: same bank/branch/account as `self::VALID_IBAN` (national
     * DC '45'), but the BBAN carries a wrong national DC ('46'). The
     * IBAN-level check digits are recomputed over that (bad) BBAN via
     * `Mod97::checkDigits()`, so the assembled IBAN passes MOD-97 while
     * still failing the ES national check.
     *
     * Mirrors `Tests\Core\ValidatorNationalTest::esIbanWithBadNationalCheckDigits()`.
     */
    private function esIbanWithBadNationalCheckDigits(): string
    {
        $bank    = '2100';
        $branch  = '0418';
        $badDc   = '46'; // correct value would be '45'
        $account = '0200051332';

        $bban = $bank . $branch . $badDc . $account;

        $checkDigits = (new Mod97())->checkDigits('ES', $bban);

        return 'ES' . $checkDigits . $bban;
    }
}
