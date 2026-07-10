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
}
