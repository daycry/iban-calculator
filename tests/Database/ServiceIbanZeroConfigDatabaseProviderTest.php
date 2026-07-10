<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Iban\Config\Iban as IbanConfig;
use Daycry\Iban\Config\Services as IbanServices;

/**
 * Regression test for the re-review finding: `Config\Iban::$dbGroup` used to
 * default to the literal string `'default'`, and `Config\Services::iban()`'s
 * `'database'` branch always passed it into `new BankModel($config->table,
 * $config->dbGroup)`. Because `BankModel` treats any non-empty string as an
 * explicit override, that meant the zero-config `provider = 'database'` setup
 * ALWAYS opened a connection to the literal `'default'` group -- the bundled
 * `Config\Database::$default` MySQLi placeholder (empty credentials) -- even
 * under `ENVIRONMENT === 'testing'`, instead of transparently deferring to
 * CI4's environment-aware fallback (`Database\Config::connect(null)`, which
 * resolves to the `'tests'` SQLite group here; see
 * `vendor/codeigniter4/framework/app/Config/Database.php`'s constructor).
 *
 * That silently broke the package's own documented "opt in to `provider =
 * database`, get bank lookups for free under `DatabaseTestTrait`" quick
 * start: a consuming app's test suite would hit a live MySQLi "Access
 * denied" connection error instead of querying the SQLite `tests` group.
 *
 * This test exercises the exact zero-config path end to end: `provider` is
 * set to `'database'`, `dbGroup` is deliberately left untouched (the
 * package's documented `null` default -- see `Config\Iban::$dbGroup`), the
 * real `CreateBanksTable` migration runs against the `tests` DBGroup (via
 * {@see DatabaseTestTrait}), a row is seeded, and `service('iban')->resolve()`
 * is asked to find it. Before the fix, constructing the service here throws
 * a DB connection error; after the fix, it transparently resolves against
 * the `tests` SQLite group, proving `null` genuinely defers to CI4's
 * environment-aware default group instead of forcing `'default'`.
 *
 * @see \Daycry\Iban\Config\Iban::$dbGroup
 * @see \Daycry\Iban\Config\Services::iban()
 * @see \Daycry\Iban\Models\BankModel
 * @see tests/Database/DatabaseProviderTest.php for the equivalent proof at
 *      the `BankModel`/`DatabaseProvider` level (constructed directly,
 *      bypassing `Config\Services::iban()`).
 */
final class ServiceIbanZeroConfigDatabaseProviderTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'Daycry\Iban';
    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;

    private const SEEDED_ROW = [
        'country_code'   => 'ES',
        'bank_code'      => '2100',
        'branch_code'    => '0418',
        'bic'            => 'CAIXESBBXXX',
        'name'           => 'CaixaBank',
        'short_name'     => 'CX',
        'city'           => 'Barcelona',
        'address'        => null,
        'sepa_sct'       => 1,
        'sepa_sct_inst'  => 0,
        'sepa_sdd_core'  => 1,
        'sepa_sdd_b2b'   => null,
        'source_id'      => 'test-fixture',
        'source_version' => '2026-07-10',
        'source_license' => 'ODbL',
        'updated_at'     => '2026-07-01 00:00:00',
    ];

    protected function tearDown(): void
    {
        parent::tearDown();

        // This test mutates the shared `Config\Iban` singleton's $provider
        // (see below); undo that so later tests keep seeing the documented
        // 'null' default, and drop any shared `service('iban')` built
        // against the 'database' provider.
        config(IbanConfig::class)->provider = 'null';
        $this->resetServices();
    }

    public function testZeroConfigDatabaseProviderResolvesSeededIbanUnderTestingWithoutAConnectionError(): void
    {
        // Precondition: $dbGroup is untouched at its documented zero-config
        // default (null) -- this test's whole point is proving that value,
        // not an explicit 'tests' override, is what makes the lookup below
        // work under this (or any consuming app's) test suite.
        self::assertNull(
            config(IbanConfig::class)->dbGroup,
            'Precondition: dbGroup must be left at its zero-config (null) default for this regression test.',
        );

        config(IbanConfig::class)->provider = 'database';

        $this->hasInDatabase('banks', self::SEEDED_ROW);

        // Goes through the real `Config\Services::iban()` factory (not a
        // hand-built `DatabaseProvider`/`BankModel`), so it's the actual
        // `new BankModel($config->table, $config->dbGroup)` wiring under
        // test -- this is what threw the MySQLi "Access denied" connection
        // error before the fix, instead of resolving via the SQLite
        // `tests` group.
        $iban = IbanServices::iban(false);

        $result = $iban->resolve('ES9121000418450200051332');

        self::assertTrue($result->isResolved());
        self::assertSame('CaixaBank', $result->bankName);
        self::assertSame('CX', $result->shortName);
        self::assertSame('CAIXESBBXXX', $result->bic);
        self::assertSame('Barcelona', $result->city);
    }
}
