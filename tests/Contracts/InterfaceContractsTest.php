<?php

declare(strict_types=1);

namespace Tests\Contracts;

use Daycry\Iban\Contracts\NationalCheckValidatorInterface;
use Daycry\Iban\Contracts\ParserInterface;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\Contracts\RegistryLoaderInterface;
use Daycry\Iban\Contracts\ResolverInterface;
use Daycry\Iban\Contracts\ValidatorInterface;
use Daycry\Iban\Enums\IbanFormat;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

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
        $secondParamType = $secondParam->getType();
        self::assertInstanceOf(ReflectionNamedType::class, $secondParamType);
        self::assertSame('bool', $secondParamType->getName());
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

    public function testProviderInterfaceExists(): void
    {
        self::assertTrue(interface_exists(ProviderInterface::class));
    }

    public function testProviderInterfaceHasSupportsMethod(): void
    {
        $reflection = new ReflectionClass(ProviderInterface::class);
        self::assertTrue($reflection->hasMethod('supports'));

        $method = $reflection->getMethod('supports');
        self::assertCount(1, $method->getParameters());
        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('bool', $returnType->getName());
    }

    public function testProviderInterfaceHasFindByIbanMethod(): void
    {
        $reflection = new ReflectionClass(ProviderInterface::class);
        self::assertTrue($reflection->hasMethod('findByIban'));

        $method = $reflection->getMethod('findByIban');
        self::assertCount(1, $method->getParameters());
        $returnType = $method->getReturnType();
        self::assertNotNull($returnType);
        self::assertTrue($returnType->allowsNull());
    }

    public function testProviderInterfaceHasFindByBankCodeMethod(): void
    {
        $reflection = new ReflectionClass(ProviderInterface::class);
        self::assertTrue($reflection->hasMethod('findByBankCode'));

        $method = $reflection->getMethod('findByBankCode');
        self::assertCount(3, $method->getParameters());

        // Check third parameter has default null
        $thirdParam = $method->getParameters()[2];
        self::assertSame('branchCode', $thirdParam->getName());
        self::assertTrue($thirdParam->getType()?->allowsNull());
        self::assertTrue($thirdParam->isDefaultValueAvailable());
        self::assertNull($thirdParam->getDefaultValue());

        // Check return type is nullable
        $returnType = $method->getReturnType();
        self::assertNotNull($returnType);
        self::assertTrue($returnType->allowsNull());
    }

    public function testResolverInterfaceExists(): void
    {
        self::assertTrue(interface_exists(ResolverInterface::class));
    }

    public function testResolverInterfaceHasResolveMethod(): void
    {
        $reflection = new ReflectionClass(ResolverInterface::class);
        self::assertTrue($reflection->hasMethod('resolve'));

        $method = $reflection->getMethod('resolve');
        self::assertCount(1, $method->getParameters());

        $returnType = $method->getReturnType();
        self::assertNotNull($returnType);
    }

    public function testRegistryLoaderInterfaceExists(): void
    {
        self::assertTrue(interface_exists(RegistryLoaderInterface::class));
    }

    public function testRegistryLoaderInterfaceHasLoadMethod(): void
    {
        $reflection = new ReflectionClass(RegistryLoaderInterface::class);
        self::assertTrue($reflection->hasMethod('load'));

        $method = $reflection->getMethod('load');
        self::assertCount(0, $method->getParameters());

        $returnType = $method->getReturnType();
        self::assertNotNull($returnType);
    }

    public function testNationalCheckValidatorInterfaceExists(): void
    {
        self::assertTrue(interface_exists(NationalCheckValidatorInterface::class));
    }

    public function testNationalCheckValidatorInterfaceHasSupportsMethod(): void
    {
        $reflection = new ReflectionClass(NationalCheckValidatorInterface::class);
        self::assertTrue($reflection->hasMethod('supports'));

        $method = $reflection->getMethod('supports');
        self::assertCount(1, $method->getParameters());
        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('bool', $returnType->getName());
    }

    public function testNationalCheckValidatorInterfaceHasVerifyMethod(): void
    {
        $reflection = new ReflectionClass(NationalCheckValidatorInterface::class);
        self::assertTrue($reflection->hasMethod('verify'));

        $method = $reflection->getMethod('verify');
        self::assertCount(1, $method->getParameters());
        $returnType = $method->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertSame('bool', $returnType->getName());
    }
}
