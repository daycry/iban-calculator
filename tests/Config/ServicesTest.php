<?php

declare(strict_types=1);

namespace Tests\Config;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Iban\Config\Iban as IbanConfig;
use Daycry\Iban\Config\Services as IbanServices;
use Daycry\Iban\Iban as IbanService;
use Daycry\Iban\Models\BankModel;
use Daycry\Iban\Providers\DatabaseProvider;
use Daycry\Iban\Providers\NullProvider;
use Daycry\Iban\Resolver\Resolver;
use InvalidArgumentException;
use ReflectionProperty;

/**
 * Exercises `Config\Services::iban()` through CI4's real service discovery
 * (`service('iban')`), plus the `Config\Iban::$provider` → provider-class
 * match, and `Config\Iban`'s documented defaults.
 *
 * @see \Daycry\Iban\Config\Iban
 * @see \Daycry\Iban\Config\Services
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class ServicesTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        // Tests below mutate the shared `Config\Iban` singleton to
        // exercise the 'database' branch of the provider match (and its
        // $table/$dbGroup wiring); undo that so later tests keep seeing
        // the documented defaults, and drop any shared `service('iban')`
        // instance built against it.
        config(IbanConfig::class)->provider = 'null';
        config(IbanConfig::class)->table    = 'banks';
        config(IbanConfig::class)->dbGroup  = 'default';
        $this->resetServices();
    }

    public function testServiceIbanIsDiscoverableAndReturnsTheFacade(): void
    {
        $iban = service('iban');

        self::assertInstanceOf(IbanService::class, $iban);
    }

    public function testServiceIbanValidatesARealSpanishIbanWithoutAnyDbConfig(): void
    {
        $iban = service('iban');

        self::assertTrue($iban->isValid('ES9121000418450200051332'));
    }

    public function testServiceIbanNonSharedReturnsAFreshFacadeInstanceEachTime(): void
    {
        $first  = service('iban', false);
        $second = service('iban', false);

        self::assertInstanceOf(IbanService::class, $first);
        self::assertInstanceOf(IbanService::class, $second);
        self::assertNotSame($first, $second);
    }

    public function testDefaultProviderIsNullProviderAndResolveIsUnresolved(): void
    {
        $iban = service('iban', false);

        self::assertInstanceOf(NullProvider::class, self::resolverProviderOf($iban));

        $result = $iban->resolve('ES9121000418450200051332');
        self::assertFalse($result->isResolved());
    }

    public function testProviderMatchMapsTheDatabaseStringToDatabaseProvider(): void
    {
        config(IbanConfig::class)->provider = 'database';

        $iban = IbanServices::iban(false);

        self::assertInstanceOf(IbanService::class, $iban);
        self::assertInstanceOf(DatabaseProvider::class, self::resolverProviderOf($iban));
    }

    public function testProviderMatchSupportsACustomProviderFqcn(): void
    {
        // Any FQCN outside the 'null'/'database' shortcuts must be
        // instantiated directly by the match's default arm. Reusing
        // NullProvider's own FQCN (rather than the literal 'null' string)
        // proves that arm works without needing a bespoke fixture class.
        config(IbanConfig::class)->provider = NullProvider::class;

        $iban = IbanServices::iban(false);

        self::assertInstanceOf(NullProvider::class, self::resolverProviderOf($iban));
    }

    public function testProviderMatchThrowsForANonExistentClassName(): void
    {
        config(IbanConfig::class)->provider = 'Totally\\Bogus\\NoSuchProviderClass';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is not a valid class');

        IbanServices::iban(false);
    }

    /**
     * Proves the Fix 1 wiring: the 'database' branch of `Services::iban()`
     * doesn't just build a bare `new DatabaseProvider()` -- it constructs
     * the underlying `BankModel` from `Config\Iban::$table` /
     * `Config\Iban::$dbGroup`, so a consuming app's overrides of either
     * property actually reach the DB layer.
     *
     * @see tests/Database/DatabaseProviderTest.php for a functional,
     *      query-level proof of the `$table` override in action.
     */
    public function testDatabaseProviderBranchBuildsBankModelFromConfiguredTableAndDbGroup(): void
    {
        config(IbanConfig::class)->provider = 'database';
        config(IbanConfig::class)->table    = 'custom_banks';
        config(IbanConfig::class)->dbGroup  = 'tests';

        $iban = IbanServices::iban(false);

        $provider = self::resolverProviderOf($iban);
        self::assertInstanceOf(DatabaseProvider::class, $provider);

        $model = self::bankModelOf($provider);
        self::assertSame('custom_banks', self::tableOf($model));
        self::assertSame('tests', self::dbGroupOf($model));
    }

    public function testConfigIbanHasTheFiveDocumentedDefaults(): void
    {
        $config = new IbanConfig();

        self::assertSame('null', $config->provider);
        self::assertSame('print', $config->defaultFormat);
        self::assertFalse($config->checkNationalByDefault);
        self::assertSame('default', $config->dbGroup);
        self::assertSame('banks', $config->table);
    }

    /**
     * Reaches into the facade's private `Resolver::$provider` to assert
     * which provider class `Config\Services::iban()` actually wired up,
     * without triggering a real DB lookup (see `DatabaseProvider` test
     * above, which never calls `resolve()`).
     */
    private static function resolverProviderOf(IbanService $iban): object
    {
        $property = new ReflectionProperty(Resolver::class, 'provider');
        $property->setAccessible(true);

        return $property->getValue($iban->resolver());
    }

    /**
     * Reaches into `DatabaseProvider`'s private `$model` to assert how
     * `Services::iban()` built it (table/dbGroup), without triggering a
     * real DB lookup.
     */
    private static function bankModelOf(DatabaseProvider $provider): BankModel
    {
        $property = new ReflectionProperty(DatabaseProvider::class, 'model');
        $property->setAccessible(true);

        /** @var BankModel $model */
        $model = $property->getValue($provider);

        return $model;
    }

    private static function tableOf(BankModel $model): string
    {
        $property = new ReflectionProperty(BankModel::class, 'table');
        $property->setAccessible(true);

        /** @var string $value */
        $value = $property->getValue($model);

        return $value;
    }

    private static function dbGroupOf(BankModel $model): ?string
    {
        $property = new ReflectionProperty(BankModel::class, 'DBGroup');
        $property->setAccessible(true);

        /** @var string|null $value */
        $value = $property->getValue($model);

        return $value;
    }
}
