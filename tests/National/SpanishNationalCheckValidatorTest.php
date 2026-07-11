<?php

declare(strict_types=1);

namespace Tests\National;

use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\National\SpanishNationalCheckValidator;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 * @see .superpowers/sdd/task-26-brief.md
 */
final class SpanishNationalCheckValidatorTest extends TestCase
{
    private SpanishNationalCheckValidator $validator;
    private Parser $parser;

    protected function setUp(): void
    {
        $this->validator = new SpanishNationalCheckValidator();
        $this->parser    = new Parser(new Validator(new Registry()), new Normalizer());
    }

    // -- supports() -----------------------------------------------------------

    public function testSupportsSpainUppercase(): void
    {
        self::assertTrue($this->validator->supports('ES'));
    }

    public function testSupportsSpainIsCaseInsensitive(): void
    {
        self::assertTrue($this->validator->supports('es'));
    }

    public function testDoesNotSupportGermany(): void
    {
        self::assertFalse($this->validator->supports('DE'));
    }

    // -- verify(): real end-to-end Spanish IBAN --------------------------------

    /**
     * ES9121000418450200051332 is a real, well-formed Spanish IBAN
     * (used throughout the existing test suite, e.g. ParserTest,
     * Mod97Test). Parsing it yields bank=2100, branch=0418,
     * nationalCheckDigit=45, account=0200051332.
     *
     * Manual verification of the mod-11 algorithm against these fields
     * (weights [1,2,4,8,5,10,9,7,3,6]):
     *
     *   DC1 over '00'+'2100'+'0418' = '0021000418':
     *     digits:  0 0 2 1 0 0 0 4 1 8
     *     weights: 1 2 4 8 5 10 9 7 3 6
     *     products:0+0+8+8+0+0+0+28+3+48 = 95
     *     95 % 11 = 7  =>  r = 11 - 7 = 4  =>  DC1 = '4'
     *
     *   DC2 over '0200051332':
     *     digits:  0 2 0 0 0 5 1 3 3 2
     *     weights: 1 2 4 8 5 10 9 7 3 6
     *     products:0+4+0+0+0+50+9+21+9+12 = 105
     *     105 % 11 = 6  =>  r = 11 - 6 = 5  =>  DC2 = '5'
     *
     *   DC1.DC2 = '45', which matches the real IBAN's national check
     *   digits exactly. So this IS a real, valid, end-to-end Spanish
     *   IBAN whose national check digits verify true.
     */
    public function testVerifyReturnsTrueForRealValidSpanishIban(): void
    {
        $parsed = $this->parser->parse('ES9121000418450200051332');

        self::assertSame('45', $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsAltered(): void
    {
        $parsed = $this->parser->parse('ES9121000418450200051332');

        $tampered = $this->withNationalCheckDigit($parsed, '46');

        self::assertFalse($this->validator->verify($tampered));
    }

    // -- verify(): non-Spanish IBANs are a no-op skip --------------------------

    public function testVerifyIsANoOpSkipForNonSpanishCountry(): void
    {
        // supports('DE') is false, so verify() short-circuits to true --
        // the Validator pipeline only consults verify() for countries the
        // validator supports(); this is just documenting the contract.
        $parsed = new ParsedIban(
            countryCode: 'DE',
            checkDigits: '89',
            bban: '370400440532013000',
            bankIdentifier: '37040044',
            branchIdentifier: null,
            accountNumber: '0532013000',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'DE89370400440532013000',
        );

        self::assertTrue($this->validator->verify($parsed));
    }

    // -- verify(): defensive guard for malformed ES ParsedIban -----------------

    public function testVerifyReturnsFalseWhenBranchIdentifierIsNull(): void
    {
        // A real Parser-produced ES ParsedIban always has a branch and a
        // national check digit (per the registry). This fixture is
        // deliberately contrived to exercise the defensive guard clause.
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418450200051332',
            bankIdentifier: '2100',
            branchIdentifier: null,
            accountNumber: '0200051332',
            nationalCheckDigit: '45',
            sepaCountry: true,
            electronic: 'ES9121000418450200051332',
        );

        self::assertFalse($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418450200051332',
            bankIdentifier: '2100',
            branchIdentifier: '0418',
            accountNumber: '0200051332',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'ES9121000418450200051332',
        );

        self::assertFalse($this->validator->verify($parsed));
    }

    // -- verify(): edge case, remainder 11 -> '0' ------------------------------

    /**
     * All-zero bank/branch/account: every weighted product is 0, so
     * sum = 0, 0 % 11 = 0, r = 11 - 0 = 11 -> DC = '0' for BOTH DC1 and
     * DC2. This exercises the `$r === 11` branch of controlDigit().
     */
    public function testVerifyEdgeCaseRemainderElevenYieldsZero(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '00',
            bban: '00000000000000000000',
            bankIdentifier: '0000',
            branchIdentifier: '0000',
            accountNumber: '0000000000',
            nationalCheckDigit: '00',
            sepaCountry: true,
            electronic: 'ES0000000000000000000000',
        );

        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyEdgeCaseRemainderElevenFailsWithWrongDigit(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '00',
            bban: '00000000000000000000',
            bankIdentifier: '0000',
            branchIdentifier: '0000',
            accountNumber: '0000000000',
            nationalCheckDigit: '01',
            sepaCountry: true,
            electronic: 'ES0000000000000000000000',
        );

        self::assertFalse($this->validator->verify($parsed));
    }

    // -- verify(): edge case, remainder 10 -> '1' ------------------------------

    /**
     * Reuses the real bank/branch (2100/0418) from the ES9121... IBAN
     * above, whose DC1 is independently known to be '4' (see the
     * worked calculation on testVerifyReturnsTrueForRealValidSpanishIban).
     *
     * Account '1000000000' has a single 1 in the units-weight position
     * (weight 1 at index 0):
     *   digits:  1 0 0 0 0 0 0 0 0 0
     *   weights: 1 2 4 8 5 10 9 7 3 6
     *   sum = 1*1 = 1
     *   1 % 11 = 1  =>  r = 11 - 1 = 10  =>  DC2 = '1'
     *
     * This exercises the `$r === 10` branch of controlDigit().
     * nationalCheckDigit is therefore '4' . '1' = '41'.
     */
    public function testVerifyEdgeCaseRemainderTenYieldsOne(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418411000000000',
            bankIdentifier: '2100',
            branchIdentifier: '0418',
            accountNumber: '1000000000',
            nationalCheckDigit: '41',
            sepaCountry: true,
            electronic: 'ES9121000418411000000000',
        );

        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyEdgeCaseRemainderTenFailsWithWrongDigit(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418401000000000',
            bankIdentifier: '2100',
            branchIdentifier: '0418',
            accountNumber: '1000000000',
            nationalCheckDigit: '40',
            sepaCountry: true,
            electronic: 'ES9121000418401000000000',
        );

        self::assertFalse($this->validator->verify($parsed));
    }

    private function withNationalCheckDigit(ParsedIban $iban, string $nationalCheckDigit): ParsedIban
    {
        return new ParsedIban(
            countryCode: $iban->countryCode,
            checkDigits: $iban->checkDigits,
            bban: $iban->bban,
            bankIdentifier: $iban->bankIdentifier,
            branchIdentifier: $iban->branchIdentifier,
            accountNumber: $iban->accountNumber,
            nationalCheckDigit: $nationalCheckDigit,
            sepaCountry: $iban->sepaCountry,
            electronic: $iban->electronic,
        );
    }
}
