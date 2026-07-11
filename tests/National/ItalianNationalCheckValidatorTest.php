<?php

declare(strict_types=1);

namespace Tests\National;

use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\National\ItalianNationalCheckValidator;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\TestCase;

/**
 * @see .superpowers/sdd/task-v4b-brief.md
 * @see .superpowers/sdd/task-v4b-report.md
 */
final class ItalianNationalCheckValidatorTest extends TestCase
{
    private ItalianNationalCheckValidator $validator;
    private Parser $parser;

    protected function setUp(): void
    {
        $this->validator = new ItalianNationalCheckValidator();
        $this->parser    = new Parser(new Validator(new Registry()), new Normalizer());
    }

    // -- supports() -------------------------------------------------------

    public function testSupportsItalyUppercase(): void
    {
        self::assertTrue($this->validator->supports('IT'));
    }

    public function testSupportsItalyIsCaseInsensitive(): void
    {
        self::assertTrue($this->validator->supports('it'));
    }

    public function testSupportsSanMarinoUppercase(): void
    {
        self::assertTrue($this->validator->supports('SM'));
    }

    public function testSupportsSanMarinoIsCaseInsensitive(): void
    {
        self::assertTrue($this->validator->supports('sm'));
    }

    public function testDoesNotSupportGermany(): void
    {
        self::assertFalse($this->validator->supports('DE'));
    }

    // -- verify(): real end-to-end Italian IBAN ----------------------------

    /**
     * IT60X0542811101000000123456 is the registry's own example IBAN.
     * Parsing it yields nationalCheckDigit (CIN)='X', bank(ABI)='05428',
     * branch(CAB)='11101', account='000000123456'.
     *
     * Manual verification: the 22-char tail (ABI+CAB+account) is
     * '0542811101000000123456'; summing each char's ODD/EVEN table value
     * by 1-indexed position and taking mod 26 yields 23 -> 'X', matching
     * the real CIN exactly.
     */
    public function testVerifyReturnsTrueForRealValidItalianIban(): void
    {
        $parsed = $this->parser->parse('IT60X0542811101000000123456');

        self::assertSame('X', $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenItalianCinIsAltered(): void
    {
        $parsed = $this->parser->parse('IT60X0542811101000000123456');

        $tampered = $this->withNationalCheckDigit($parsed, 'Y');

        self::assertFalse($this->validator->verify($tampered));
    }

    // -- verify(): real end-to-end San Marino IBAN (shares IT's algorithm) -

    /**
     * SM86U0322509800000000270100 is the registry's own example IBAN for
     * San Marino, which shares the identical CIN structure/algorithm as
     * IT. Parsing it yields nationalCheckDigit (CIN)='U'.
     */
    public function testVerifyReturnsTrueForRealValidSanMarinoIban(): void
    {
        $parsed = $this->parser->parse('SM86U0322509800000000270100');

        self::assertSame('U', $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenSanMarinoCinIsAltered(): void
    {
        $parsed = $this->parser->parse('SM86U0322509800000000270100');

        $tampered = $this->withNationalCheckDigit($parsed, 'V');

        self::assertFalse($this->validator->verify($tampered));
    }

    // -- verify(): non-IT/SM IBANs are a no-op skip ------------------------

    public function testVerifyIsANoOpSkipForNonItalianCountry(): void
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

    // -- verify(): defensive guards for malformed IT ParsedIban ------------

    public function testVerifyReturnsFalseWhenBranchIdentifierIsNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'IT',
            checkDigits: '60',
            bban: 'X' . '05428' . '11101' . '000000123456',
            bankIdentifier: '05428',
            branchIdentifier: null,
            accountNumber: '000000123456',
            nationalCheckDigit: 'X',
            sepaCountry: true,
            electronic: 'IT60X0542811101000000123456',
        );

        self::assertFalse($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'IT',
            checkDigits: '60',
            bban: 'X' . '05428' . '11101' . '000000123456',
            bankIdentifier: '05428',
            branchIdentifier: '11101',
            accountNumber: '000000123456',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'IT60X0542811101000000123456',
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
