<?php

declare(strict_types=1);

namespace Tests;

use Daycry\Iban\Contracts\ParserInterface;
use Daycry\Iban\Contracts\ResolverInterface;
use Daycry\Iban\Contracts\ValidatorInterface;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Enums\IbanFormat;
use Daycry\Iban\Exceptions\InvalidIbanException;
use Daycry\Iban\Iban;
use Daycry\Iban\Resolver\Resolver;
use PHPUnit\Framework\TestCase;

final class IbanFacadeTest extends TestCase
{
    private Iban $iban;

    protected function setUp(): void
    {
        $this->iban = new Iban();
    }

    public function testDefaultConstructionWorks(): void
    {
        $facade = new Iban();
        self::assertInstanceOf(Iban::class, $facade);
    }

    public function testImplementsValidatorInterface(): void
    {
        self::assertInstanceOf(ValidatorInterface::class, $this->iban);
    }

    public function testImplementsParserInterface(): void
    {
        self::assertInstanceOf(ParserInterface::class, $this->iban);
    }

    public function testImplementsResolverInterface(): void
    {
        self::assertInstanceOf(ResolverInterface::class, $this->iban);
    }

    public function testIsValidReturnsTrueForValidIban(): void
    {
        $result = $this->iban->isValid('ES9121000418450200051332');
        self::assertTrue($result);
    }

    public function testIsValidReturnsFalseForInvalidIban(): void
    {
        $result = $this->iban->isValid('basura');
        self::assertFalse($result);
    }

    public function testParseReturnsValidParsedIban(): void
    {
        $parsed = $this->iban->parse('ES9121000418450200051332');
        self::assertInstanceOf(ParsedIban::class, $parsed);
        self::assertSame('ES', $parsed->countryCode);
        self::assertSame('91', $parsed->checkDigits);
    }

    public function testTryParseReturnsNullForInvalidIban(): void
    {
        $parsed = $this->iban->tryParse('basura');
        self::assertNull($parsed);
    }

    public function testParseThrowsForInvalidIban(): void
    {
        $this->expectException(InvalidIbanException::class);
        $this->iban->parse('basura');
    }

    public function testNormalizeRemovesSpacesAndLowercases(): void
    {
        $normalized = $this->iban->normalize('  es91 2100 0418 4502 0005 1332  ');
        self::assertSame('ES9121000418450200051332', $normalized);
    }

    public function testFormatReturnsFormattedIban(): void
    {
        $formatted = $this->iban->format('ES9121000418450200051332', IbanFormat::Print);
        self::assertIsString($formatted);
        // Print format should have spaces for readability
        self::assertStringContainsString(' ', $formatted);
    }

    public function testResolveReturnsBankResult(): void
    {
        $result = $this->iban->resolve('ES9121000418450200051332');
        self::assertNull($result->bankName); // NullProvider returns no data
        self::assertFalse($result->isResolved());
        self::assertInstanceOf(ParsedIban::class, $result->iban);
        self::assertTrue($result->iban->sepaCountry);
    }

    public function testValidatorGetterReturnsValidatorInstance(): void
    {
        $validator = $this->iban->validator();
        self::assertInstanceOf(Validator::class, $validator);
    }

    public function testParserGetterReturnsParserInstance(): void
    {
        $parser = $this->iban->parser();
        self::assertInstanceOf(Parser::class, $parser);
    }

    public function testResolverGetterReturnsResolverInstance(): void
    {
        $resolver = $this->iban->resolver();
        self::assertInstanceOf(Resolver::class, $resolver);
    }

    public function testValidateDelegatesCorrectly(): void
    {
        $result = $this->iban->validate('ES9121000418450200051332');
        self::assertTrue($result->isValid());

        $invalidResult = $this->iban->validate('basura');
        self::assertFalse($invalidResult->isValid());
    }
}
