<?php

declare(strict_types=1);

namespace Tests\National;

use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\National\FinnishNationalCheckValidator;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see .superpowers/sdd/task-v4a-brief.md
 * @see .superpowers/sdd/task-v4a-report.md
 */
final class FinnishNationalCheckValidatorTest extends TestCase
{
    private FinnishNationalCheckValidator $validator;
    private Parser $parser;

    protected function setUp(): void
    {
        $this->validator = new FinnishNationalCheckValidator();
        $this->parser    = new Parser(new Validator(new Registry()), new Normalizer());
    }

    // -- supports() -------------------------------------------------------

    public function testSupportsFinlandUppercase(): void
    {
        self::assertTrue($this->validator->supports('FI'));
    }

    public function testSupportsFinlandIsCaseInsensitive(): void
    {
        self::assertTrue($this->validator->supports('fi'));
    }

    public function testDoesNotSupportGermany(): void
    {
        self::assertFalse($this->validator->supports('DE'));
    }

    // -- verify(): real end-to-end Finnish IBAN ----------------------------

    /**
     * FI2112345600000785 is the registry's own example IBAN. Parsing it
     * yields bank='123', account='4560000078', nationalCheckDigit='5'.
     *
     * Manual verification: thirteen = '123' . '4560000078' =
     * '1234560000078'; Luhn (mod-10, doubling every second digit from the
     * right) over the first 13 digits yields check digit '5', matching
     * the real national check digit exactly.
     */
    public function testVerifyReturnsTrueForRealValidFinnishIban(): void
    {
        $parsed = $this->parser->parse('FI2112345600000785');

        self::assertSame('5', $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    /**
     * Three additional real Nordea Finland test-account IBANs found
     * during research, used as extra corroboration beyond the single
     * registry example.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function additionalRealIbanProvider(): array
    {
        return [
            ['FI4819503000000010', '0'],
            ['FI0819503000000051', '1'],
            ['FI8319503000004327', '7'],
        ];
    }

    #[DataProvider('additionalRealIbanProvider')]
    public function testVerifyReturnsTrueForAdditionalRealFinnishIbans(string $iban, string $expectedCheck): void
    {
        $parsed = $this->parser->parse($iban);

        self::assertSame($expectedCheck, $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsAltered(): void
    {
        $parsed = $this->parser->parse('FI2112345600000785');

        $tampered = $this->withNationalCheckDigit($parsed, '6');

        self::assertFalse($this->validator->verify($tampered));
    }

    // -- verify(): non-Finnish IBANs are a no-op skip -----------------------

    public function testVerifyIsANoOpSkipForNonFinnishCountry(): void
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

    // -- verify(): defensive guard for malformed FI ParsedIban -------------

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'FI',
            checkDigits: '21',
            bban: '12345600000078',
            bankIdentifier: '123',
            branchIdentifier: null,
            accountNumber: '4560000078',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'FI2112345600000785',
        );

        self::assertFalse($this->validator->verify($parsed));
    }

    // -- verify(): edge case, sum already a multiple of 10 -> check '0' ----

    public function testVerifyEdgeCaseSumMultipleOfTenYieldsZero(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'FI',
            checkDigits: '00',
            bban: '00000000000000',
            bankIdentifier: '000',
            branchIdentifier: null,
            accountNumber: '0000000000',
            nationalCheckDigit: '0',
            sepaCountry: true,
            electronic: 'FI0000000000000000',
        );

        self::assertTrue($this->validator->verify($parsed));
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
