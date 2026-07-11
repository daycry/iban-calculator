<?php

declare(strict_types=1);

namespace Tests\National;

use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\National\BelgianNationalCheckValidator;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\TestCase;

/**
 * @see .superpowers/sdd/task-v4a-brief.md
 * @see .superpowers/sdd/task-v4a-report.md
 */
final class BelgianNationalCheckValidatorTest extends TestCase
{
    private BelgianNationalCheckValidator $validator;
    private Parser $parser;

    protected function setUp(): void
    {
        $this->validator = new BelgianNationalCheckValidator();
        $this->parser    = new Parser(new Validator(new Registry()), new Normalizer());
    }

    // -- supports() -------------------------------------------------------

    public function testSupportsBelgiumUppercase(): void
    {
        self::assertTrue($this->validator->supports('BE'));
    }

    public function testSupportsBelgiumIsCaseInsensitive(): void
    {
        self::assertTrue($this->validator->supports('be'));
    }

    public function testDoesNotSupportGermany(): void
    {
        self::assertFalse($this->validator->supports('DE'));
    }

    // -- verify(): real end-to-end Belgian IBAN ----------------------------

    /**
     * BE68539007547034 is the registry's own example IBAN, and it is a
     * widely cited real/canonical Belgian test IBAN. Parsing it yields
     * bank='539', account='0075470', nationalCheckDigit='34'.
     *
     * Manual verification: first10 = '539' . '0075470' = '5390075470';
     * 5390075470 % 97 = 34, matching the real national check digits
     * exactly.
     */
    public function testVerifyReturnsTrueForRealValidBelgianIban(): void
    {
        $parsed = $this->parser->parse('BE68539007547034');

        self::assertSame('34', $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsAltered(): void
    {
        $parsed = $this->parser->parse('BE68539007547034');

        $tampered = $this->withNationalCheckDigit($parsed, '35');

        self::assertFalse($this->validator->verify($tampered));
    }

    // -- verify(): non-Belgian IBANs are a no-op skip ----------------------

    public function testVerifyIsANoOpSkipForNonBelgianCountry(): void
    {
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

    // -- verify(): defensive guard for malformed BE ParsedIban -------------

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'BE',
            checkDigits: '68',
            bban: '539007547034',
            bankIdentifier: '539',
            branchIdentifier: null,
            accountNumber: '0075470',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'BE68539007547034',
        );

        self::assertFalse($this->validator->verify($parsed));
    }

    // -- verify(): edge case, remainder 0 -> 97 ----------------------------

    /**
     * bank='000', account='0000000' => first10 = '0000000000', which is
     * a multiple of 97 (0 % 97 = 0), so the expected national check is
     * '97' (never '00').
     */
    public function testVerifyEdgeCaseRemainderZeroYieldsNinetySeven(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'BE',
            checkDigits: '00',
            bban: '000000000097',
            bankIdentifier: '000',
            branchIdentifier: null,
            accountNumber: '0000000',
            nationalCheckDigit: '97',
            sepaCountry: true,
            electronic: 'BE00000000000097',
        );

        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyEdgeCaseRemainderZeroFailsWithWrongDigit(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'BE',
            checkDigits: '00',
            bban: '000000000000',
            bankIdentifier: '000',
            branchIdentifier: null,
            accountNumber: '0000000',
            nationalCheckDigit: '00',
            sepaCountry: true,
            electronic: 'BE00000000000000',
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
