<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\IbanBicCrossChecker;
use Daycry\Iban\Core\Mod97;
use Daycry\Iban\DTO\ParsedBic;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Iban;
use PHPUnit\Framework\TestCase;

/**
 * @see \Daycry\Iban\Core\IbanBicCrossChecker
 */
final class IbanBicCrossCheckerTest extends TestCase
{
    private IbanBicCrossChecker $checker;
    private Iban $iban;

    protected function setUp(): void
    {
        $this->checker = new IbanBicCrossChecker();
        $this->iban    = new Iban();
    }

    private function parsedIban(string $iban): ParsedIban
    {
        return $this->iban->parse($iban);
    }

    private function parsedBic(string $bic): ParsedBic
    {
        return $this->iban->parseBic($bic);
    }

    // -- 4-alpha-bank-code country (GB): both directions ----------------------

    public function testGbIbanWithMatchingBicIsCoherent(): void
    {
        $violations = $this->checker->check(
            $this->parsedIban('GB29NWBK60161331926819'), // bank NWBK
            $this->parsedBic('NWBKGB2L')                 // institution NWBK
        );

        self::assertSame([], $violations);
    }

    public function testGbIbanWithNonMatchingBankBicEmitsBankMismatch(): void
    {
        $violations = $this->checker->check(
            $this->parsedIban('GB29NWBK60161331926819'), // bank NWBK
            $this->parsedBic('BARCGB22')                 // institution BARC, same country GB
        );

        self::assertCount(1, $violations);
        self::assertSame(ViolationCode::BicIbanBankMismatch, $violations[0]->code);
    }

    public function testNlIbanWithMatchingBicIsCoherent(): void
    {
        $violations = $this->checker->check(
            $this->parsedIban('NL91ABNA0417164300'), // bank ABNA
            $this->parsedBic('ABNANL2A')             // institution ABNA
        );

        self::assertSame([], $violations);
    }

    public function testNlIbanWithNonMatchingBankBicEmitsBankMismatch(): void
    {
        $violations = $this->checker->check(
            $this->parsedIban('NL91ABNA0417164300'), // bank ABNA
            $this->parsedBic('INGBNL2A')             // institution INGB
        );

        self::assertCount(1, $violations);
        self::assertSame(ViolationCode::BicIbanBankMismatch, $violations[0]->code);
    }

    // -- numeric-bank-code country (ES/DE) emits NO bank violation ------------

    public function testEsIbanEmitsNoBankViolationEvenWhenInstitutionDiffers(): void
    {
        // ES bank code is 2100 (numeric); BIC institution is CAIX. There is no
        // structural relationship, so NO bank violation may be raised.
        $violations = $this->checker->check(
            $this->parsedIban('ES9121000418450200051332'), // bank 2100
            $this->parsedBic('CAIXESBB')                   // institution CAIX, country ES
        );

        self::assertSame([], $violations);
    }

    public function testDeIbanEmitsNoBankViolation(): void
    {
        // DE bank code is 37040044 (numeric); DEUT institution can never be
        // structurally compared — coherent as long as the country matches.
        $violations = $this->checker->check(
            $this->parsedIban('DE89370400440532013000'),
            $this->parsedBic('DEUTDEFF')
        );

        self::assertSame([], $violations);
    }

    // -- country mismatch -----------------------------------------------------

    public function testDocumentedMartiniqueBankAcceptsItsEightAndElevenCharacterBic(): void
    {
        $bban = '13088' . '00001' . '00000000001' . '00'; // Synthetic account.
        $iban = 'FR' . (new Mod97())->checkDigits('FR', $bban) . $bban;
        foreach (['BNPAMQMX', 'BNPAMQMXXXX'] as $bic) {
            self::assertTrue($this->iban->isValid($iban));
            self::assertTrue($this->iban->isValidBic($bic));
            self::assertTrue($this->iban->validateIbanAndBic($iban, $bic)->isValid());
        }
    }

    public function testMartiniqueExceptionDoesNotAcceptAnotherBankOrAnUnlistedBic(): void
    {
        foreach ([['13089', 'BNPAMQMXXXX'], ['13088', 'TESTMQMX'], ['13088', 'BNPAMQMX123']] as [$bank, $bic]) {
            $bban = $bank . '00001' . '00000000001' . '00';
            $iban = 'FR' . (new Mod97())->checkDigits('FR', $bban) . $bban;
            $result = $this->iban->validateIbanAndBic($iban, $bic);
            self::assertSame(ViolationCode::BicIbanCountryMismatch, $result->firstViolation()?->code);
        }
    }

    public function testCountryMismatchEsIbanDeBic(): void
    {
        $violations = $this->checker->check(
            $this->parsedIban('ES9121000418450200051332'), // ES
            $this->parsedBic('DEUTDEFF')                   // DE
        );

        self::assertCount(1, $violations);
        self::assertSame(ViolationCode::BicIbanCountryMismatch, $violations[0]->code);
    }
}
