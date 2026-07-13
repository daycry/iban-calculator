<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Iban\Database\Seeds\IsoCountriesSeeder;
use Daycry\Iban\Models\IsoCountryModel;
use Daycry\Iban\Providers\DatabaseIsoCountryLoader;
use Daycry\Iban\Registry\IsoCountryRegistry;
use Daycry\Iban\Registry\PhpIsoCountryLoader;

/**
 * Proves the optional database-backed ISO 3166-1 source is a faithful mirror
 * of the compiled list: the {@see IsoCountriesSeeder} loads every row of
 * {@see PhpIsoCountryLoader} into the `iso_countries` table, and
 * {@see DatabaseIsoCountryLoader} reads it back into the identical shape — so
 * an {@see IsoCountryRegistry} built on either source behaves the same.
 *
 * @see \Daycry\Iban\Database\Migrations\CreateIsoCountriesTable
 */
final class IsoCountriesSeederTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'Daycry\Iban';
    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;

    public function testSeederPopulatesEveryCompiledRow(): void
    {
        $this->seed(IsoCountriesSeeder::class);

        $expected = count((new PhpIsoCountryLoader())->load());

        self::assertSame($expected, $this->db->table('iso_countries')->countAllResults());
        self::assertSame(249, $expected);

        $this->seeInDatabase('iso_countries', [
            'alpha2'  => 'ES',
            'name'    => 'Spain',
            'alpha3'  => 'ESP',
            'numeric' => '724',
        ]);
        $this->seeInDatabase('iso_countries', [
            'alpha2'  => 'US',
            'alpha3'  => 'USA',
            'numeric' => '840',
        ]);
    }

    public function testDatabaseLoaderReturnsShapeIdenticalToPhpLoader(): void
    {
        $this->seed(IsoCountriesSeeder::class);

        $php = (new PhpIsoCountryLoader())->load();
        $db  = (new DatabaseIsoCountryLoader(new IsoCountryModel()))->load();

        ksort($php);
        ksort($db);

        self::assertSame($php, $db);
    }

    public function testRegistryBuiltOnDatabaseLoaderResolves(): void
    {
        $this->seed(IsoCountriesSeeder::class);

        $registry = new IsoCountryRegistry(new DatabaseIsoCountryLoader(new IsoCountryModel()));

        self::assertTrue($registry->has('US'));
        self::assertSame('Spain', $registry->get('ES')->name);
        self::assertSame(249, $registry->count());
    }

    public function testRunningTheSeederTwiceDoesNotDuplicate(): void
    {
        $this->seed(IsoCountriesSeeder::class);
        $this->seed(IsoCountriesSeeder::class);

        $expected = count((new PhpIsoCountryLoader())->load());

        self::assertSame($expected, $this->db->table('iso_countries')->countAllResults());
    }
}
