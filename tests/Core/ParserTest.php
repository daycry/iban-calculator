<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\Formatter;
use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Enums\IbanFormat;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Exceptions\InvalidIbanException;
use Daycry\Iban\Registry\Registry;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class ParserTest extends TestCase
{
    private Parser $parser;

    protected function setUp(): void
    {
        $this->parser = new Parser(new Validator(new Registry()), new Normalizer(), new Formatter());
    }

    // -- parse(): field slicing per country --------------------------------

    public function testParseSpainSlicesAllFields(): void
    {
        $parsed = $this->parser->parse('ES9121000418450200051332');

        self::assertSame('ES', $parsed->countryCode);
        self::assertSame('91', $parsed->checkDigits);
        self::assertSame('2100', $parsed->bankIdentifier);
        self::assertSame('0418', $parsed->branchIdentifier);
        self::assertSame('0200051332', $parsed->accountNumber);
        self::assertSame('45', $parsed->nationalCheckDigit);
        self::assertTrue($parsed->sepaCountry);
        self::assertSame('ES9121000418450200051332', $parsed->electronic);
    }

    public function testParseGermanyHasNoBranchOrNationalCheck(): void
    {
        // Germany's BBAN has no branch or national-check field in the registry.
        $parsed = $this->parser->parse('DE89370400440532013000');

        self::assertSame('DE', $parsed->countryCode);
        self::assertNull($parsed->branchIdentifier);
        self::assertSame('37040044', $parsed->bankIdentifier);
        self::assertSame('0532013000', $parsed->accountNumber);
        self::assertNull($parsed->nationalCheckDigit);
    }

    public function testParseNetherlandsHasNoBranchAndAlphaBankIdentifier(): void
    {
        // The Netherlands' BBAN has no branch field; the bank identifier is
        // alphabetic (BIC-derived), unlike ES/DE's numeric bank codes.
        $parsed = $this->parser->parse('NL91ABNA0417164300');

        self::assertSame('NL', $parsed->countryCode);
        self::assertNull($parsed->branchIdentifier);
        self::assertSame('ABNA', $parsed->bankIdentifier);
    }

    // -- parse(): throws on invalid input -----------------------------------

    public function testParseThrowsInvalidIbanExceptionOnGarbageInput(): void
    {
        // 'basura' normalizes to 'BASURA'; 'BA' (Bosnia and Herzegovina) IS a
        // registered country, so the pipeline gets past UnknownCountry, but
        // 'BASURA' (6 chars) mismatches BA's required 20-char IBAN length --
        // so BadLength is the violation actually raised, per pipeline order.
        try {
            $this->parser->parse('basura');
            self::fail('Expected InvalidIbanException was not thrown.');
        } catch (InvalidIbanException $e) {
            self::assertSame(ViolationCode::BadLength, $e->result()->firstViolation()?->code);
        }
    }

    public function testParseThrowsInvalidIbanExceptionOnBadChecksum(): void
    {
        // Correct ES length/structure, but check digits 91 -> 90 fail MOD-97.
        try {
            $this->parser->parse('ES9021000418450200051332');
            self::fail('Expected InvalidIbanException was not thrown.');
        } catch (InvalidIbanException $e) {
            self::assertSame(ViolationCode::ChecksumFailed, $e->result()->firstViolation()?->code);
        }
    }

    // -- tryParse() -----------------------------------------------------------

    public function testTryParseReturnsNullOnGarbageInput(): void
    {
        self::assertNull($this->parser->tryParse('basura'));
    }

    public function testTryParseReturnsParsedIbanOnValidInput(): void
    {
        $parsed = $this->parser->tryParse('ES9121000418450200051332');

        self::assertInstanceOf(ParsedIban::class, $parsed);
        self::assertSame('ES9121000418450200051332', $parsed->electronic);
    }

    // -- normalize() -----------------------------------------------------------

    public function testNormalizeStripsSpacesAndUppercases(): void
    {
        self::assertSame(
            'ES9121000418450200051332',
            $this->parser->normalize('  es91 2100 0418 4502 0005 1332 ')
        );
    }

    // -- format() -----------------------------------------------------------

    public function testFormatPrintFromString(): void
    {
        self::assertSame(
            'ES91 2100 0418 4502 0005 1332',
            $this->parser->format('ES9121000418450200051332', IbanFormat::Print)
        );
    }

    public function testFormatAnonymizedFromParsedIban(): void
    {
        $parsed = $this->parser->parse('ES9121000418450200051332');

        self::assertSame(
            'ES******************1332',
            $this->parser->format($parsed, IbanFormat::Anonymized)
        );
    }

    public function testFormatElectronicFromRawStringNormalizesFirst(): void
    {
        // format() does not require validity -- it formats the normalized
        // form for presentation, even from an untrimmed/lowercase string.
        self::assertSame(
            'ES9121000418450200051332',
            $this->parser->format(' es91 2100 0418 4502 0005 1332 ', IbanFormat::Electronic)
        );
    }

    public function testFormatDefaultsToPrint(): void
    {
        self::assertSame(
            'ES91 2100 0418 4502 0005 1332',
            $this->parser->format('ES9121000418450200051332')
        );
    }
}
