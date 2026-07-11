<?php

declare(strict_types=1);

namespace Tests\National;

use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\National\PortugueseNationalCheckValidator;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\TestCase;

/**
 * @see .superpowers/sdd/task-v4a-brief.md
 * @see .superpowers/sdd/task-v4a-report.md
 */
final class PortugueseNationalCheckValidatorTest extends TestCase
{
    private PortugueseNationalCheckValidator $validator;
    private Parser $parser;

    protected function setUp(): void
    {
        $this->validator = new PortugueseNationalCheckValidator();
        $this->parser    = new Parser(new Validator(new Registry()), new Normalizer());
    }

    // -- supports() -------------------------------------------------------

    public function testSupportsPortugalUppercase(): void
    {
        self::assertTrue($this->validator->supports('PT'));
    }

    public function testSupportsPortugalIsCaseInsensitive(): void
    {
        self::assertTrue($this->validator->supports('pt'));
    }

    public function testDoesNotSupportGermany(): void
    {
        self::assertFalse($this->validator->supports('DE'));
    }

    // -- verify(): real end-to-end Portuguese IBAN -------------------------

    /**
     * PT50000201231234567890154 is the registry's own example IBAN.
     * Parsing it yields bank='0002', branch='0123', account='12345678901',
     * nationalCheckDigit='54'.
     *
     * Manual verification: nineteen = '0002' . '0123' . '12345678901' =
     * '0002012312345678901'; weighted sum (weights 73,17,89,38,62,45,53,
     * 15,50,5,49,34,81,76,27,90,9,30,3) = 2469; 2469 % 97 = 44;
     * 98 - 44 = 54, matching the real national check digits exactly.
     */
    public function testVerifyReturnsTrueForRealValidPortugueseIban(): void
    {
        $parsed = $this->parser->parse('PT50000201231234567890154');

        self::assertSame('54', $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    /**
     * A second real, independently MOD-97-valid Portuguese IBAN (a
     * Millennium BCP sample found during research), used as extra
     * corroboration of the algorithm beyond the single registry example.
     */
    public function testVerifyReturnsTrueForASecondRealPortugueseIban(): void
    {
        $parsed = $this->parser->parse('PT50003300000017351398905');

        self::assertSame('05', $parsed->nationalCheckDigit);
        self::assertTrue($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsAltered(): void
    {
        $parsed = $this->parser->parse('PT50000201231234567890154');

        $tampered = $this->withNationalCheckDigit($parsed, '55');

        self::assertFalse($this->validator->verify($tampered));
    }

    // -- verify(): non-Portuguese IBANs are a no-op skip -------------------

    public function testVerifyIsANoOpSkipForNonPortugueseCountry(): void
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

    // -- verify(): defensive guards for malformed PT ParsedIban ------------

    public function testVerifyReturnsFalseWhenBranchIdentifierIsNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'PT',
            checkDigits: '50',
            bban: '000201231234567890154',
            bankIdentifier: '0002',
            branchIdentifier: null,
            accountNumber: '12345678901',
            nationalCheckDigit: '54',
            sepaCountry: true,
            electronic: 'PT50000201231234567890154',
        );

        self::assertFalse($this->validator->verify($parsed));
    }

    public function testVerifyReturnsFalseWhenNationalCheckDigitIsNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'PT',
            checkDigits: '50',
            bban: '000201231234567890154',
            bankIdentifier: '0002',
            branchIdentifier: '0123',
            accountNumber: '12345678901',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'PT50000201231234567890154',
        );

        self::assertFalse($this->validator->verify($parsed));
    }

    // -- verify(): edge cases, collapsing 98 -> 0 and 97 -> 1 --------------

    /**
     * All-zero bank/branch/account: every weighted product is 0, so
     * sum = 0, 0 % 97 = 0, check = 98 - 0 = 98 -> collapses to '00'.
     */
    public function testVerifyEdgeCaseNinetyEightCollapsesToZero(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'PT',
            checkDigits: '00',
            bban: '0000000000000000000000',
            bankIdentifier: '0000',
            branchIdentifier: '0000',
            accountNumber: '00000000000',
            nationalCheckDigit: '00',
            sepaCountry: true,
            electronic: 'PT000000000000000000000000',
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
