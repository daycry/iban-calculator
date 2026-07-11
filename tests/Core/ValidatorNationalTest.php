<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\Mod97;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end tests for the national check-digit hook (step 8 of the
 * pipeline) wired to its default validator map (T-27: ES by default).
 *
 * @see .superpowers/sdd/task-27-brief.md
 */
final class ValidatorNationalTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        // Real Registry, default $nationalValidators (ES wired in by T-27).
        $this->validator = new Validator(new Registry());
    }

    /**
     * Builds a MOD-97-valid ES IBAN whose national (mod-11) check digits are
     * wrong: same bank/branch/account as the canonical valid fixture
     * ('ES9121000418450200051332', national DC '45'), but the BBAN carries
     * a wrong national DC ('46'). The IBAN-level check digits are then
     * recomputed over that (bad) BBAN via Mod97::checkDigits(), so the
     * assembled IBAN passes MOD-97 while still failing the ES national
     * check (which independently recomputes '45' from bank+branch+account
     * and compares it against the '46' baked into the BBAN).
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

    public function testWrongNationalCheckDigitsFailWhenCheckNationalIsRequested(): void
    {
        $iban = $this->esIbanWithBadNationalCheckDigits();

        $result    = $this->validator->validate($iban, checkNational: true);
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::NationalCheckFailed, $violation->code);
        self::assertSame('iban.violation.national_check_failed', $violation->messageKey);
        self::assertSame('The national check digits are invalid.', $violation->message);
    }

    public function testWrongNationalCheckDigitsAreIgnoredWhenCheckNationalIsNotRequested(): void
    {
        $iban = $this->esIbanWithBadNationalCheckDigits();

        // Without the flag, the national validator is never invoked: the
        // IBAN is structurally sound and MOD-97-valid, so it is valid.
        self::assertTrue($this->validator->validate($iban)->isValid());
    }

    public function testCountryWithoutANationalValidatorIsSkippedEvenWithTheFlag(): void
    {
        // No DE entry in the default $nationalValidators map => silent skip.
        $result = $this->validator->validate('DE89370400440532013000', checkNational: true);

        self::assertTrue($result->isValid());
    }

    public function testValidEsIbanPassesTheNationalCheckWhenRequested(): void
    {
        $result = $this->validator->validate('ES9121000418450200051332', checkNational: true);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    // -- BE: registered by default (T-V4a) -------------------------------

    /**
     * Builds a MOD-97-valid BE IBAN whose national (mod-97 of the first
     * 10 BBAN digits) check digits are wrong: same bank/account as the
     * real fixture (BE68539007547034, national check '34'), but the BBAN
     * carries a wrong national check ('35'). The IBAN-level check digits
     * are then recomputed over that (bad) BBAN via Mod97::checkDigits().
     */
    private function beIbanWithBadNationalCheckDigits(): string
    {
        $bank    = '539';
        $account = '0075470';
        $badDc   = '35'; // correct value would be '34'

        $bban = $bank . $account . $badDc;

        $checkDigits = (new Mod97())->checkDigits('BE', $bban);

        return 'BE' . $checkDigits . $bban;
    }

    public function testWrongBelgianNationalCheckDigitsFailWhenCheckNationalIsRequested(): void
    {
        $iban      = $this->beIbanWithBadNationalCheckDigits();
        $result    = $this->validator->validate($iban, checkNational: true);
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::NationalCheckFailed, $violation->code);
    }

    public function testValidBeIbanPassesTheNationalCheckWhenRequested(): void
    {
        $result = $this->validator->validate('BE68539007547034', checkNational: true);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    // -- PT: registered by default (T-V4a) --------------------------------

    /**
     * Builds a MOD-97-valid PT IBAN whose national (weighted mod-97 over
     * bank+branch+account) check digits are wrong: same bank/branch/
     * account as the real fixture (PT50000201231234567890154, national
     * check '54'), but the BBAN carries a wrong national check ('55').
     */
    private function ptIbanWithBadNationalCheckDigits(): string
    {
        $bank    = '0002';
        $branch  = '0123';
        $account = '12345678901';
        $badDc   = '55'; // correct value would be '54'

        $bban = $bank . $branch . $account . $badDc;

        $checkDigits = (new Mod97())->checkDigits('PT', $bban);

        return 'PT' . $checkDigits . $bban;
    }

    public function testWrongPortugueseNationalCheckDigitsFailWhenCheckNationalIsRequested(): void
    {
        $iban      = $this->ptIbanWithBadNationalCheckDigits();
        $result    = $this->validator->validate($iban, checkNational: true);
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::NationalCheckFailed, $violation->code);
    }

    public function testValidPtIbanPassesTheNationalCheckWhenRequested(): void
    {
        $result = $this->validator->validate('PT50000201231234567890154', checkNational: true);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    // -- SI: registered by default (T-V4a) --------------------------------

    /**
     * Builds a MOD-97-valid SI IBAN whose national (mod-97-of-13-digits,
     * ISO 7064 style) check digits are wrong: same bank/account as the
     * real fixture (SI56263300012039086, national check '86'), but the
     * BBAN carries a wrong national check ('87').
     */
    private function siIbanWithBadNationalCheckDigits(): string
    {
        $bank    = '26330';
        $account = '00012039';
        $badDc   = '87'; // correct value would be '86'

        $bban = $bank . $account . $badDc;

        $checkDigits = (new Mod97())->checkDigits('SI', $bban);

        return 'SI' . $checkDigits . $bban;
    }

    public function testWrongSlovenianNationalCheckDigitsFailWhenCheckNationalIsRequested(): void
    {
        $iban      = $this->siIbanWithBadNationalCheckDigits();
        $result    = $this->validator->validate($iban, checkNational: true);
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::NationalCheckFailed, $violation->code);
    }

    public function testValidSiIbanPassesTheNationalCheckWhenRequested(): void
    {
        $result = $this->validator->validate('SI56263300012039086', checkNational: true);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    // -- FI: registered by default (T-V4a) --------------------------------

    /**
     * Builds a MOD-97-valid FI IBAN whose national (Luhn mod-10) check
     * digit is wrong: same bank/account as the real fixture
     * (FI2112345600000785, national check '5'), but the BBAN carries a
     * wrong national check ('6').
     */
    private function fiIbanWithBadNationalCheckDigits(): string
    {
        $bank    = '123';
        $account = '4560000078';
        $badDc   = '6'; // correct value would be '5'

        $bban = $bank . $account . $badDc;

        $checkDigits = (new Mod97())->checkDigits('FI', $bban);

        return 'FI' . $checkDigits . $bban;
    }

    public function testWrongFinnishNationalCheckDigitFailsWhenCheckNationalIsRequested(): void
    {
        $iban      = $this->fiIbanWithBadNationalCheckDigits();
        $result    = $this->validator->validate($iban, checkNational: true);
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::NationalCheckFailed, $violation->code);
    }

    public function testValidFiIbanPassesTheNationalCheckWhenRequested(): void
    {
        $result = $this->validator->validate('FI2112345600000785', checkNational: true);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    // -- FR: registered by default (T-V4b) --------------------------------

    /**
     * Builds a MOD-97-valid FR IBAN whose national (RIB key) check digits
     * are wrong: same bank/branch/account as the real fixture
     * (FR1420041010050500013M02606, national check '06'), but the BBAN
     * carries a wrong national check ('07').
     */
    private function frIbanWithBadNationalCheckDigits(): string
    {
        $bank    = '20041';
        $branch  = '01005';
        $account = '0500013M026';
        $badDc   = '07'; // correct value would be '06'

        $bban = $bank . $branch . $account . $badDc;

        $checkDigits = (new Mod97())->checkDigits('FR', $bban);

        return 'FR' . $checkDigits . $bban;
    }

    public function testWrongFrenchNationalCheckDigitsFailWhenCheckNationalIsRequested(): void
    {
        $iban      = $this->frIbanWithBadNationalCheckDigits();
        $result    = $this->validator->validate($iban, checkNational: true);
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::NationalCheckFailed, $violation->code);
    }

    public function testValidFrIbanPassesTheNationalCheckWhenRequested(): void
    {
        $result = $this->validator->validate('FR1420041010050500013M02606', checkNational: true);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    // -- MC: registered by default (T-V4b), shares FR's RIB algorithm -----

    /**
     * Builds a MOD-97-valid MC IBAN whose national (RIB key) check digits
     * are wrong: same bank/branch/account as the real fixture
     * (MC5811222000010123456789030, national check '30'), but the BBAN
     * carries a wrong national check ('31').
     */
    private function mcIbanWithBadNationalCheckDigits(): string
    {
        $bank    = '11222';
        $branch  = '00001';
        $account = '01234567890';
        $badDc   = '31'; // correct value would be '30'

        $bban = $bank . $branch . $account . $badDc;

        $checkDigits = (new Mod97())->checkDigits('MC', $bban);

        return 'MC' . $checkDigits . $bban;
    }

    public function testWrongMonacoNationalCheckDigitsFailWhenCheckNationalIsRequested(): void
    {
        $iban      = $this->mcIbanWithBadNationalCheckDigits();
        $result    = $this->validator->validate($iban, checkNational: true);
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::NationalCheckFailed, $violation->code);
    }

    public function testValidMcIbanPassesTheNationalCheckWhenRequested(): void
    {
        $result = $this->validator->validate('MC5811222000010123456789030', checkNational: true);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    // -- IT: registered by default (T-V4b) --------------------------------

    /**
     * Builds a MOD-97-valid IT IBAN whose national (CIN) check letter is
     * wrong: same bank(ABI)/branch(CAB)/account as the real fixture
     * (IT60X0542811101000000123456, CIN 'X'), but the BBAN carries a wrong
     * CIN ('Y'). Note the CIN is the FIRST character of the BBAN for IT.
     */
    private function itIbanWithBadNationalCheckDigits(): string
    {
        $bank    = '05428';
        $branch  = '11101';
        $account = '000000123456';
        $badCin  = 'Y'; // correct value would be 'X'

        $bban = $badCin . $bank . $branch . $account;

        $checkDigits = (new Mod97())->checkDigits('IT', $bban);

        return 'IT' . $checkDigits . $bban;
    }

    public function testWrongItalianCinFailsWhenCheckNationalIsRequested(): void
    {
        $iban      = $this->itIbanWithBadNationalCheckDigits();
        $result    = $this->validator->validate($iban, checkNational: true);
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::NationalCheckFailed, $violation->code);
    }

    public function testValidItIbanPassesTheNationalCheckWhenRequested(): void
    {
        $result = $this->validator->validate('IT60X0542811101000000123456', checkNational: true);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    // -- SM: registered by default (T-V4b), shares IT's CIN algorithm -----

    /**
     * Builds a MOD-97-valid SM IBAN whose national (CIN) check letter is
     * wrong: same bank/branch/account as the real fixture
     * (SM86U0322509800000000270100, CIN 'U'), but the BBAN carries a wrong
     * CIN ('V').
     */
    private function smIbanWithBadNationalCheckDigits(): string
    {
        $bank    = '03225';
        $branch  = '09800';
        $account = '000000270100';
        $badCin  = 'V'; // correct value would be 'U'

        $bban = $badCin . $bank . $branch . $account;

        $checkDigits = (new Mod97())->checkDigits('SM', $bban);

        return 'SM' . $checkDigits . $bban;
    }

    public function testWrongSanMarinoCinFailsWhenCheckNationalIsRequested(): void
    {
        $iban      = $this->smIbanWithBadNationalCheckDigits();
        $result    = $this->validator->validate($iban, checkNational: true);
        $violation = $result->firstViolation();

        self::assertFalse($result->isValid());
        self::assertNotNull($violation);
        self::assertSame(ViolationCode::NationalCheckFailed, $violation->code);
    }

    public function testValidSmIbanPassesTheNationalCheckWhenRequested(): void
    {
        $result = $this->validator->validate('SM86U0322509800000000270100', checkNational: true);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->violations());
    }

    // -- EE: deliberately NOT registered (T-V4a) ---------------------------

    public function testEstoniaIsSkippedEvenWithTheFlagBecauseItWasDeliberatelyOmitted(): void
    {
        // No EE entry in the default $nationalValidators map (see
        // .superpowers/sdd/task-v4a-report.md for why) => silent skip.
        $result = $this->validator->validate('EE382200221020145685', checkNational: true);

        self::assertTrue($result->isValid());
    }
}
