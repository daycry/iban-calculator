<?php

declare(strict_types=1);

namespace Tests\Contracts;

use Daycry\Iban\Contracts\ParserInterface;
use Daycry\Iban\Contracts\ValidatorInterface;
use Daycry\Iban\Enums\IbanFormat;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Light reflection test to verify interface contracts.
 *
 * Ensures each interface declares its methods with the expected parameter counts, types, and defaults.
 */
final class InterfaceContractsTest extends TestCase
{
    public function testValidatorInterfaceExists(): void
    {
        self::assertTrue(interface_exists(ValidatorInterface::class));
    }

    public function testParserInterfaceExists(): void
    {
        self::assertTrue(interface_exists(ParserInterface::class));
    }

    public function testValidatorInterfaceHasValidateMethod(): void
    {
        $reflection = new ReflectionClass(ValidatorInterface::class);
        self::assertTrue($reflection->hasMethod('validate'));

        $method = $reflection->getMethod('validate');
        self::assertCount(2, $method->getParameters());

        // Check second parameter is bool with default false
        $secondParam = $method->getParameters()[1];
        self::assertSame('checkNational', $secondParam->getName());
        self::assertTrue($secondParam->getType()?->getName() === 'bool');
        self::assertTrue($secondParam->isDefaultValueAvailable());
        self::assertFalse($secondParam->getDefaultValue());
    }

    public function testValidatorInterfaceHasIsValidMethod(): void
    {
        $reflection = new ReflectionClass(ValidatorInterface::class);
        self::assertTrue($reflection->hasMethod('isValid'));

        $method = $reflection->getMethod('isValid');
        self::assertCount(1, $method->getParameters());
    }

    public function testParserInterfaceHasNormalizeMethod(): void
    {
        $reflection = new ReflectionClass(ParserInterface::class);
        self::assertTrue($reflection->hasMethod('normalize'));

        $method = $reflection->getMethod('normalize');
        self::assertCount(1, $method->getParameters());
    }

    public function testParserInterfaceHasParseMethod(): void
    {
        $reflection = new ReflectionClass(ParserInterface::class);
        self::assertTrue($reflection->hasMethod('parse'));

        $method = $reflection->getMethod('parse');
        self::assertCount(1, $method->getParameters());
    }

    public function testParserInterfaceHasTryParseMethod(): void
    {
        $reflection = new ReflectionClass(ParserInterface::class);
        self::assertTrue($reflection->hasMethod('tryParse'));

        $method = $reflection->getMethod('tryParse');
        self::assertCount(1, $method->getParameters());

        // Verify return type is nullable
        $returnType = $method->getReturnType();
        self::assertNotNull($returnType);
        self::assertTrue($returnType->allowsNull());
    }

    public function testParserInterfaceHasFormatMethod(): void
    {
        $reflection = new ReflectionClass(ParserInterface::class);
        self::assertTrue($reflection->hasMethod('format'));

        $method = $reflection->getMethod('format');
        self::assertCount(2, $method->getParameters());

        // Check second parameter has default IbanFormat::Print
        $secondParam = $method->getParameters()[1];
        self::assertSame('f', $secondParam->getName());
        self::assertTrue($secondParam->isDefaultValueAvailable());
        self::assertSame(IbanFormat::Print, $secondParam->getDefaultValue());
    }
}
