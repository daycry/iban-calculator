<?php

declare(strict_types=1);

namespace Tests\Config;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Iban\Config\Iban as IbanConfig;
use Daycry\Iban\Config\Services as IbanServices;
use Daycry\Iban\Models\IsoCountryModel;
use Daycry\Iban\Providers\DatabaseIsoCountryLoader;
use Daycry\Iban\Registry\IsoCountryRegistry;
use Daycry\Iban\Registry\PhpIsoCountryLoader;
use ReflectionProperty;

/**
 * Exercises `Config\Services::isoCountries()` and its
 * `Config\Iban::$isoCountrySource` → loader selection.
 *
 * @see \Daycry\Iban\Config\Services::isoCountries()
 * @see \Daycry\Iban\Config\Iban::$isoCountrySource
 */
final class IsoCountriesServiceTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        Factories::reset('config');
        $this->resetServices();

        parent::tearDown();
    }

    public function testServiceIsDiscoverableAndReturnsTheRegistry(): void
    {
        $registry = service('isoCountries');

        self::assertInstanceOf(IsoCountryRegistry::class, $registry);
    }

    public function testDefaultSourceIsThePhpLoaderAndWorksWithNoDatabase(): void
    {
        // Default config: isoCountrySource = 'php'. No DatabaseTestTrait here,
        // so there is no DB at all — the registry must still resolve.
        $registry = IbanServices::isoCountries(false);

        self::assertInstanceOf(PhpIsoCountryLoader::class, self::loaderOf($registry));
        self::assertTrue($registry->has('US'));
        self::assertSame(249, $registry->count());
    }

    public function testConfigDefaultsAreDocumented(): void
    {
        $config = new IbanConfig();

        self::assertSame('php', $config->isoCountrySource);
        self::assertSame('iso_countries', $config->isoCountryTable);
    }

    public function testDatabaseSourceSelectsTheDatabaseLoaderWiredFromConfig(): void
    {
        $override = new class () extends IbanConfig {
            public string $isoCountrySource = 'database';
            public string $isoCountryTable  = 'custom_iso';
        };
        Factories::injectMock('config', 'Iban', $override);

        // Wiring only — assert the loader/model, never triggers a query.
        $registry = IbanServices::isoCountries(false);

        $loader = self::loaderOf($registry);
        self::assertInstanceOf(DatabaseIsoCountryLoader::class, $loader);

        $model = self::modelOf($loader);
        self::assertSame('custom_iso', self::tableOf($model));
    }

    private static function loaderOf(IsoCountryRegistry $registry): object
    {
        $property = new ReflectionProperty(IsoCountryRegistry::class, 'loader');
        $property->setAccessible(true);

        /** @var object $loader */
        $loader = $property->getValue($registry);

        return $loader;
    }

    private static function modelOf(DatabaseIsoCountryLoader $loader): IsoCountryModel
    {
        $property = new ReflectionProperty(DatabaseIsoCountryLoader::class, 'model');
        $property->setAccessible(true);

        /** @var IsoCountryModel $model */
        $model = $property->getValue($loader);

        return $model;
    }

    private static function tableOf(IsoCountryModel $model): string
    {
        $property = new ReflectionProperty(IsoCountryModel::class, 'table');
        $property->setAccessible(true);

        /** @var string $value */
        $value = $property->getValue($model);

        return $value;
    }
}
