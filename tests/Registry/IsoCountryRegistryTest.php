<?php

declare(strict_types=1);

namespace Tests\Registry;

use Daycry\Iban\Contracts\IsoCountryLoaderInterface;
use Daycry\Iban\DTO\IsoCountry;
use Daycry\Iban\Registry\IsoCountryRegistry;
use Daycry\Iban\Registry\PhpIsoCountryLoader;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

/**
 * @see \Daycry\Iban\Registry\IsoCountryRegistry
 */
final class IsoCountryRegistryTest extends TestCase
{
    public function testHasReturnsTrueForKnownUppercaseCode(): void
    {
        self::assertTrue((new IsoCountryRegistry())->has('ES'));
    }

    public function testHasIsCaseInsensitive(): void
    {
        self::assertTrue((new IsoCountryRegistry())->has('es'));
    }

    public function testHasReturnsTrueForNonIbanCountry(): void
    {
        // The whole point of this registry: it recognises countries that
        // have BICs but no IBAN (and are absent from the IBAN Registry).
        $registry = new IsoCountryRegistry();

        self::assertTrue($registry->has('US'));
        self::assertTrue($registry->has('JP'));
        self::assertTrue($registry->has('CA'));
        self::assertTrue($registry->has('AU'));
    }

    public function testHasReturnsFalseForUnknownCode(): void
    {
        self::assertFalse((new IsoCountryRegistry())->has('ZZ'));
    }

    public function testHasReturnsFalseForKosovoUserAssignedCode(): void
    {
        // XK is user-assigned, not officially assigned — excluded here.
        self::assertFalse((new IsoCountryRegistry())->has('XK'));
    }

    public function testGetReturnsHydratedIsoCountry(): void
    {
        $country = (new IsoCountryRegistry())->get('ES');

        self::assertInstanceOf(IsoCountry::class, $country);
        self::assertSame('ES', $country->alpha2);
        self::assertSame('Spain', $country->name);
        self::assertSame('ESP', $country->alpha3);
        self::assertSame('724', $country->numeric);
    }

    public function testGetIsCaseInsensitive(): void
    {
        $country = (new IsoCountryRegistry())->get('us');

        self::assertSame('US', $country->alpha2);
        self::assertSame('USA', $country->alpha3);
        self::assertSame('840', $country->numeric);
    }

    public function testGetThrowsOutOfBoundsForUnknownCode(): void
    {
        $this->expectException(OutOfBoundsException::class);

        (new IsoCountryRegistry())->get('ZZ');
    }

    public function testAllReturnsFullMapKeyedByAlpha2(): void
    {
        $all = (new IsoCountryRegistry())->all();

        self::assertNotSame([], $all);
        self::assertArrayHasKey('DE', $all);
        self::assertInstanceOf(IsoCountry::class, $all['DE']);
        self::assertSame('Germany', $all['DE']->name);
    }

    public function testCountMatchesTheLoadedData(): void
    {
        $registry = new IsoCountryRegistry();

        self::assertSame(count((new PhpIsoCountryLoader())->load()), $registry->count());
        self::assertSame(249, $registry->count());
    }

    public function testUsesTheInjectedLoader(): void
    {
        $loader = new class () implements IsoCountryLoaderInterface {
            public function load(): array
            {
                return [
                    'ZZ' => ['name' => 'Testland', 'alpha3' => 'ZZZ', 'numeric' => '999'],
                ];
            }
        };

        $registry = new IsoCountryRegistry($loader);

        self::assertSame(1, $registry->count());
        self::assertTrue($registry->has('ZZ'));
        self::assertSame('Testland', $registry->get('ZZ')->name);
    }

    public function testVersionConstantMentionsIndependentAuthorship(): void
    {
        self::assertNotSame('', IsoCountryRegistry::VERSION);
        self::assertStringContainsStringIgnoringCase('independent', IsoCountryRegistry::VERSION);
    }

    public function testPhpIsoCountryLoaderReturnsRawShapeForEs(): void
    {
        $raw = (new PhpIsoCountryLoader())->load();

        self::assertArrayHasKey('ES', $raw);
        self::assertSame(['name' => 'Spain', 'alpha3' => 'ESP', 'numeric' => '724'], $raw['ES']);
    }
}
