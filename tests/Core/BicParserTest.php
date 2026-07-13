<?php

declare(strict_types=1);

namespace Tests\Core;

use Daycry\Iban\Core\BicParser;
use Daycry\Iban\Core\BicValidator;
use Daycry\Iban\DTO\ParsedBic;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Exceptions\InvalidBicException;
use Daycry\Iban\Registry\IsoCountryRegistry;
use PHPUnit\Framework\TestCase;

final class BicParserTest extends TestCase
{
    private BicParser $parser;

    protected function setUp(): void
    {
        $this->parser = new BicParser(new BicValidator(new IsoCountryRegistry()));
    }

    public function testParseBic8SlicesFields(): void
    {
        $parsed = $this->parser->parse('CHASUS33');

        self::assertInstanceOf(ParsedBic::class, $parsed);
        self::assertSame('CHAS', $parsed->institutionCode);
        self::assertSame('US', $parsed->countryCode);
        self::assertSame('33', $parsed->locationCode);
        self::assertNull($parsed->branchCode);
        self::assertTrue($parsed->isPrimaryOffice());
    }

    public function testParseBic11SlicesBranch(): void
    {
        $parsed = $this->parser->parse('DEUTDEFF500');

        self::assertSame('500', $parsed->branchCode);
        self::assertFalse($parsed->isPrimaryOffice());
    }

    public function testParseNormalizesLowercaseAndSpacedInput(): void
    {
        $parsed = $this->parser->parse(' caix es bb xxx ');

        self::assertSame('CAIXESBBXXX', $parsed->bic);
        self::assertSame('XXX', $parsed->branchCode);
    }

    public function testParseThrowsInvalidBicExceptionOnBadLength(): void
    {
        try {
            $this->parser->parse('CHASUS3');
            self::fail('Expected InvalidBicException was not thrown.');
        } catch (InvalidBicException $e) {
            self::assertSame(ViolationCode::BicBadLength, $e->result()->firstViolation()?->code);
        }
    }

    public function testParseThrowsInvalidBicExceptionOnUnknownCountry(): void
    {
        try {
            $this->parser->parse('CHASZZ33');
            self::fail('Expected InvalidBicException was not thrown.');
        } catch (InvalidBicException $e) {
            self::assertSame(ViolationCode::BicUnknownCountry, $e->result()->firstViolation()?->code);
        }
    }

    public function testTryParseReturnsNullOnInvalidInput(): void
    {
        self::assertNull($this->parser->tryParse('nonsense'));
    }

    public function testTryParseReturnsParsedBicOnValidInput(): void
    {
        $parsed = $this->parser->tryParse('CAIXESBBXXX');

        self::assertInstanceOf(ParsedBic::class, $parsed);
        self::assertSame('CAIXESBBXXX', $parsed->bic);
    }

    public function testNormalizeDelegatesToValidator(): void
    {
        self::assertSame('CHASUS33', $this->parser->normalize(' chas us 33 '));
    }
}
