<?php

declare(strict_types=1);

namespace Tests\Registry;

use Daycry\Iban\Registry\CountryStructure;
use Daycry\Iban\Registry\PhpRegistryLoader;
use Daycry\Iban\Registry\Registry;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §6.1
 */
final class RegistryTest extends TestCase
{
    public function testHasReturnsTrueForKnownUppercaseCountryCode(): void
    {
        $registry = new Registry();

        self::assertTrue($registry->has('ES'));
    }

    public function testHasIsCaseInsensitive(): void
    {
        $registry = new Registry();

        self::assertTrue($registry->has('es'));
    }

    public function testHasReturnsFalseForUnknownCountryCode(): void
    {
        $registry = new Registry();

        self::assertFalse($registry->has('ZZ'));
    }

    public function testGetReturnsHydratedCountryStructureForES(): void
    {
        $registry = new Registry();

        $structure = $registry->get('ES');

        self::assertInstanceOf(CountryStructure::class, $structure);
        self::assertSame('ES', $structure->countryCode);
        self::assertSame(24, $structure->ibanLength);
        self::assertSame('4!n4!n2!n10!n', $structure->bbanStructure);
        self::assertSame([4, 4], $structure->bank);
        self::assertSame([8, 4], $structure->branch);
        self::assertSame([14, 10], $structure->account);
        self::assertSame([12, 2], $structure->nationalCheck);
        self::assertTrue($structure->sepa);
        self::assertSame('ES9121000418450200051332', $structure->ibanExampleElectronic);
    }

    public function testGetIsCaseInsensitive(): void
    {
        $registry = new Registry();

        $structure = $registry->get('es');

        self::assertSame('ES', $structure->countryCode);
    }

    public function testGetThrowsOutOfBoundsExceptionForUnknownCountryCode(): void
    {
        $registry = new Registry();

        $this->expectException(OutOfBoundsException::class);

        $registry->get('ZZ');
    }

    public function testAllReturnsNonEmptyMapKeyedByCountryCode(): void
    {
        $registry = new Registry();

        $all = $registry->all();

        self::assertNotSame([], $all);
        self::assertArrayHasKey('ES', $all);
        self::assertInstanceOf(CountryStructure::class, $all['ES']);
        self::assertSame('ES', $all['ES']->countryCode);
    }

    public function testVersionConstantIsNonEmptyAndMentionsIndependentAuthorship(): void
    {
        self::assertNotSame('', Registry::VERSION);
        self::assertStringContainsStringIgnoringCase('independent', Registry::VERSION);
    }

    public function testPhpRegistryLoaderLoadReturnsRawEsArray(): void
    {
        $loader = new PhpRegistryLoader();

        $raw = $loader->load();

        self::assertArrayHasKey('ES', $raw);
        self::assertSame(24, $raw['ES']['iban_length']);
        self::assertSame('4!n4!n2!n10!n', $raw['ES']['bban_structure']);
        self::assertSame([4, 4], $raw['ES']['bank']);
        self::assertSame([8, 4], $raw['ES']['branch']);
        self::assertSame([14, 10], $raw['ES']['account']);
        self::assertSame([12, 2], $raw['ES']['national_check']);
        self::assertTrue($raw['ES']['sepa']);
        self::assertSame('ES9121000418450200051332', $raw['ES']['example']);
    }
}
