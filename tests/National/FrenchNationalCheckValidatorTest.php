<?php

declare(strict_types=1);

namespace Tests\National;

use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\National\FrenchNationalCheckValidator;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\TestCase;

/**
 * @see .superpowers/sdd/task-v4b-brief.md
 * @see .superpowers/sdd/task-v4b-report.md
 */
final class FrenchNationalCheckValidatorTest extends TestCase
{
    private FrenchNationalCheckValidator $validator;
    private Parser $parser;

    protected function setUp(): void
    {
        $this->validator = new FrenchNationalCheckValidator();
        $this->parser    = new Parser(new Validator(new Registry()), new Normalizer());
    }

    // -- supports() -------------------------------------------------------

    public function testSupportsFranceUppercase(): void
    {
        self::assertTrue($this->validator->supports('FR'));
    }

    public function testSupportsFranceIsCaseInsensitive(): void
    {
        self::assertTrue($this->validator->supports('fr'));
    }

    public function testSupportsMonacoUppercase(): void
    {
        self::assertTrue($this->validator->supports('MC'));
    }

    public function testSupportsMonacoIsCaseInsensitive(): void
    {
        self::assertTrue($this->validator->supports('mc'));
    }

    public function testDoesNotSupportGermany(): void
    {
        self::assertFalse($this->validator->supports('DE'));
    }

    // -- verify(): real end-to-end French IBAN -----------------------------

    /**
     * FR1420041010050500013M02606 is the registry's own example IBAN.
     * Parsing it yields bank='20041', branch='01005',
     * account='0500013M026', nationalCheckDigit='06'.
     *
     * Manual verification: the account letter 'M' maps to digit 4 via the
     * RIB table, giving digit-string '05000134026'. Reducing each of
     * bank/branch/account-digits mod 97 gives 59/35/45; RIB key =
     * 97 - ((89*59 + 15*35 + 3*45) mod 97) = 97 - 91 = 6 -> '06', matching
     * the real national check digits exactly.
     */
    public function testVerifyReturnsTrueForRealValidFrenchIban(): void
    {
        $parsed = $this->parser->parse('FR1420041010050500013M02606');

        self::assertSame('0500013M026', $parsed->accountNumber);
        self::assertSame('06', $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenFrenchNationalCheckDigitIsAltered(): void
    {
        $parsed = $this->parser->parse('FR1420041010050500013M02606');

        $tampered = $this->withNationalCheckDigit($parsed, '07');

        self::assertFalse($this->validator->verify($tampered));
    }

    // -- verify(): real end-to-end Monaco IBAN (shares FR's algorithm) -----

    /**
     * MC5811222000010123456789030 is the registry's own example IBAN for
     * Monaco, which shares the identical RIB structure/algorithm as FR.
     * Parsing it yields bank='11222', branch='00001',
     * account='01234567890', nationalCheckDigit='30'.
     */
    public function testVerifyReturnsTrueForRealValidMonacoIban(): void
    {
        $parsed = $this->parser->parse('MC5811222000010123456789030');

        self::assertSame('30', $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenMonacoNationalCheckDigitIsAltered(): void
    {
        $parsed = $this->parser->parse('MC5811222000010123456789030');

        $tampered = $this->withNationalCheckDigit($parsed, '31');

        self::assertFalse($this->validator->verify($tampered));
    }

    // -- verify(): non-FR/MC IBANs are a no-op skip ------------------------

    public function testVerifyIsANoOpSkipForNonFrenchCountry(): void
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

    // -- verify(): defensive guards for malformed FR ParsedIban ------------

    public function testVerifyReturnsFalseWhenBranchIdentifierIsNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'FR',
            checkDigits: '14',
            bban: '2004101005' . '0500013M026' . '06',
            bankIdentifier: '20041',
            branchIdentifier: null,
            accountNumber: '0500013M026',
            nationalCheckDigit: '06',
            sepaCountry: true,
            electronic: 'FR1420041010050500013M02606',
        );

        self::assertFalse($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'FR',
            checkDigits: '14',
            bban: '2004101005' . '0500013M026' . '06',
            bankIdentifier: '20041',
            branchIdentifier: '01005',
            accountNumber: '0500013M026',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'FR1420041010050500013M02606',
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
