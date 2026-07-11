<?php

declare(strict_types=1);

namespace Tests\National;

use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\National\SlovenianNationalCheckValidator;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see .superpowers/sdd/task-v4a-brief.md
 * @see .superpowers/sdd/task-v4a-report.md
 */
final class SlovenianNationalCheckValidatorTest extends TestCase
{
    private SlovenianNationalCheckValidator $validator;
    private Parser $parser;

    protected function setUp(): void
    {
        $this->validator = new SlovenianNationalCheckValidator();
        $this->parser    = new Parser(new Validator(new Registry()), new Normalizer());
    }

    // -- supports() -------------------------------------------------------

    public function testSupportsSloveniaUppercase(): void
    {
        self::assertTrue($this->validator->supports('SI'));
    }

    public function testSupportsSloveniaIsCaseInsensitive(): void
    {
        self::assertTrue($this->validator->supports('si'));
    }

    public function testDoesNotSupportGermany(): void
    {
        self::assertFalse($this->validator->supports('DE'));
    }

    // -- verify(): real end-to-end Slovenian IBAN --------------------------

    /**
     * SI56263300012039086 is the registry's own example IBAN. Parsing it
     * yields bank='26330', account='00012039', nationalCheckDigit='86'.
     *
     * Manual verification: thirteen = '26330' . '00012039' = '2633000120390';
     * 2633000120390 * 100 % 97 = 12; 98 - 12 = 86, matching the real
     * national check digits exactly.
     */
    public function testVerifyReturnsTrueForRealValidSlovenianIban(): void
    {
        $parsed = $this->parser->parse('SI56263300012039086');

        self::assertSame('86', $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    /**
     * Two additional real, independently MOD-97-valid Slovenian IBANs
     * found during research, used as extra corroboration beyond the
     * single registry example.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function additionalRealIbanProvider(): array
    {
        return [
            ['SI56191000000123438', '38'],
            ['SI56029000000200020', '20'],
        ];
    }

    #[DataProvider('additionalRealIbanProvider')]
    public function testVerifyReturnsTrueForAdditionalRealSlovenianIbans(string $iban, string $expectedCheck): void
    {
        $parsed = $this->parser->parse($iban);

        self::assertSame($expectedCheck, $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsAltered(): void
    {
        $parsed = $this->parser->parse('SI56263300012039086');

        $tampered = $this->withNationalCheckDigit($parsed, '87');

        self::assertFalse($this->validator->verify($tampered));
    }

    // -- verify(): non-Slovenian IBANs are a no-op skip --------------------

    public function testVerifyIsANoOpSkipForNonSlovenianCountry(): void
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

    // -- verify(): defensive guard for malformed SI ParsedIban -------------

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'SI',
            checkDigits: '56',
            bban: '26330001203908',
            bankIdentifier: '26330',
            branchIdentifier: null,
            accountNumber: '00012039',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'SI56263300012039086',
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
