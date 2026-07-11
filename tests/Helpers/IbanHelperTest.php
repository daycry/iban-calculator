<?php

declare(strict_types=1);

namespace Tests\Helpers;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Iban\Config\Iban as IbanConfig;
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
 * @see .superpowers/sdd/task-v3-brief.md (V-3: `$defaultFormat` /
 *      `$checkNationalByDefault` wiring)
 */
final class IbanHelperTest extends CIUnitTestCase
{
    private const VALID_IBAN = 'ES9121000418450200051332';

    protected function setUp(): void
    {
        parent::setUp();

        helper('iban');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // A handful of tests below mutate the shared `Config\Iban`
        // singleton to prove `$defaultFormat`/`$checkNationalByDefault`
        // are actually consumed; undo that so later tests keep seeing the
        // documented defaults (mirrors `Tests\Config\ServicesTest`).
        config(IbanConfig::class)->defaultFormat         = 'print';
        config(IbanConfig::class)->checkNationalByDefault = false;
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

    /**
     * V-3: with no explicit `$format`, `iban_format()` now consults
     * `Config\Iban::$defaultFormat` instead of hardcoding `'print'`.
     */
    public function testIbanFormatUsesConfiguredDefaultFormatWhenNoneGiven(): void
    {
        config(IbanConfig::class)->defaultFormat = 'electronic';

        self::assertSame(self::VALID_IBAN, iban_format(self::VALID_IBAN));
    }

    /**
     * An explicit `$format` argument always wins over the config default.
     */
    public function testIbanFormatExplicitArgOverridesConfiguredDefaultFormat(): void
    {
        config(IbanConfig::class)->defaultFormat = 'electronic';

        self::assertSame(
            'ES91 2100 0418 4502 0005 1332',
            iban_format(self::VALID_IBAN, 'print'),
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
     * V-3: with no explicit `$checkNational`, `iban_validate()` now
     * consults `Config\Iban::$checkNationalByDefault` instead of
     * hardcoding `false`. With the documented default (`false`), behavior
     * is unchanged from before this task.
     */
    public function testIbanValidateDefaultsToConfiguredCheckNationalByDefaultFalse(): void
    {
        self::assertTrue(iban_validate($this->esIbanWithBadNationalCheckDigits())->isValid());
    }

    public function testIbanValidateHonorsConfiguredCheckNationalByDefaultTrue(): void
    {
        config(IbanConfig::class)->checkNationalByDefault = true;

        self::assertFalse(iban_validate($this->esIbanWithBadNationalCheckDigits())->isValid());
    }

    /**
     * An explicit `checkNational: false` argument always wins over a
     * config default of `true` — proves the sentinel is `null`, not a
     * falsy check (`??`, not `?:`).
     */
    public function testIbanValidateExplicitFalseOverridesConfiguredCheckNationalByDefaultTrue(): void
    {
        config(IbanConfig::class)->checkNationalByDefault = true;

        $result = iban_validate($this->esIbanWithBadNationalCheckDigits(), checkNational: false);

        self::assertTrue($result->isValid());
    }

    /**
     * V-3: `iban_is_valid()` gains the same `$checkNational` parameter as
     * `iban_validate()`/the facade's `validate()`, defaulting to
     * `Config\Iban::$checkNationalByDefault` (documented default: `false`).
     */
    public function testIbanIsValidDefaultsToConfiguredCheckNationalByDefaultFalse(): void
    {
        self::assertTrue(iban_is_valid($this->esIbanWithBadNationalCheckDigits()));
    }

    public function testIbanIsValidHonorsConfiguredCheckNationalByDefaultTrue(): void
    {
        config(IbanConfig::class)->checkNationalByDefault = true;

        self::assertFalse(iban_is_valid($this->esIbanWithBadNationalCheckDigits()));
    }

    public function testIbanIsValidExplicitFalseOverridesConfiguredCheckNationalByDefaultTrue(): void
    {
        config(IbanConfig::class)->checkNationalByDefault = true;

        self::assertTrue(iban_is_valid($this->esIbanWithBadNationalCheckDigits(), false));
    }

    /**
     * `iban_valid()` is a straight alias of `iban_is_valid()`: the config
     * default and explicit-override behavior must match exactly.
     */
    public function testIbanValidAliasHonorsConfiguredCheckNationalByDefaultTrue(): void
    {
        config(IbanConfig::class)->checkNationalByDefault = true;

        self::assertFalse(iban_valid($this->esIbanWithBadNationalCheckDigits()));
    }

    public function testIbanValidAliasExplicitFalseOverridesConfiguredCheckNationalByDefaultTrue(): void
    {
        config(IbanConfig::class)->checkNationalByDefault = true;

        self::assertTrue(iban_valid($this->esIbanWithBadNationalCheckDigits(), false));
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
