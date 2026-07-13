<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database as DatabaseConfig;
use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\Database\Seeds\BanksSeeder;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\Iban;
use Daycry\Iban\Models\BankModel;
use Daycry\Iban\Providers\DatabaseProvider;
use Daycry\Iban\Registry\Registry;
use Daycry\Iban\Resolver\Resolver;

/**
 * End-to-end DB test: runs the real `CreateBanksTable` migration against a
 * SQLite `:memory:` database (CodeIgniter's `tests` DBGroup), then exercises
 * `BankModel` + `DatabaseProvider` + `Resolver` against it.
 *
 * `$namespace = 'Daycry\Iban'` tells {@see DatabaseTestTrait} to run this
 * package's own migrations (found via the `Daycry\Iban\` => `src/` PSR-4
 * mapping) instead of the framework's bundled `Tests\Support` fixtures.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §7
 */
final class DatabaseProviderTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace    = 'Daycry\Iban';
    protected $DBGroup      = 'tests';
    protected $migrate      = true;
    protected $migrateOnce  = false;
    protected $refresh      = true;

    private const SEEDED_ROW = [
        'country_code'    => 'ES',
        'bank_code'       => '2100',
        'branch_code'     => '0418',
        'bic'             => 'CAIXESBBXXX',
        'name'            => 'CaixaBank',
        'short_name'      => 'CX',
        'city'            => 'Barcelona',
        'address'         => null,
        'sepa_sct'        => 1,
        'sepa_sct_inst'   => 0,
        'sepa_sdd_core'   => 1,
        'sepa_sdd_b2b'    => null,
        'source_id'       => 'test-fixture',
        'source_version'  => '2026-07-10',
        'source_license'  => 'ODbL',
        'updated_at'      => '2026-07-01 00:00:00',
    ];

    // A second row whose stored BIC is only 8 chars (no branch segment),
    // under a distinct natural key so it never collides with SEEDED_ROW's
    // unique (country_code, bank_code, branch_code). Used to prove the BIC8
    // match resolves an 8-char row from an 11-char query.
    private const SEEDED_BIC8_ROW = [
        'country_code'   => 'DE',
        'bank_code'      => '50070010',
        'branch_code'    => null,
        'bic'            => 'DEUTDEFF',
        'name'           => 'Deutsche Bank',
        'short_name'     => 'DB',
        'city'           => 'Frankfurt',
        'address'        => null,
        'sepa_sct'       => 1,
        'sepa_sct_inst'  => null,
        'sepa_sdd_core'  => null,
        'sepa_sdd_b2b'   => null,
        'source_id'      => 'test-fixture',
        'source_version' => '2026-07-10',
        'source_license' => 'ODbL',
        'updated_at'     => '2026-07-01 00:00:00',
    ];

    public function testMigrationCreatesBanksTableWithAllColumns(): void
    {
        self::assertTrue($this->db->tableExists('banks'));

        $expectedColumns = [
            'id', 'country_code', 'bank_code', 'branch_code', 'bic', 'name',
            'short_name', 'city', 'address', 'sepa_sct', 'sepa_sct_inst',
            'sepa_sdd_core', 'sepa_sdd_b2b', 'source_id', 'source_version',
            'source_license', 'updated_at',
        ];

        self::assertSame($expectedColumns, $this->db->getFieldNames('banks'));
    }

    public function testMigrationCreatesTheThreeExpectedIndexes(): void
    {
        $indexes = $this->db->getIndexData('banks');

        $nonPrimary = array_values(array_filter(
            $indexes,
            static fn (object $index): bool => $index->type !== 'PRIMARY',
        ));

        self::assertCount(3, $nonPrimary, 'Expected exactly 3 non-primary indexes on banks.');

        $unique = array_values(array_filter($nonPrimary, static fn (object $index): bool => $index->type === 'UNIQUE'));
        self::assertCount(1, $unique);
        self::assertSame(['country_code', 'bank_code', 'branch_code'], $unique[0]->fields);

        $plainIndexFields = array_map(
            static fn (object $index): array => $index->fields,
            array_values(array_filter($nonPrimary, static fn (object $index): bool => $index->type === 'INDEX')),
        );
        self::assertContainsEquals(['country_code'], $plainIndexFields);
        self::assertContainsEquals(['bic'], $plainIndexFields);
    }

    public function testBanksSeederInsertsNoRows(): void
    {
        $this->seed(BanksSeeder::class);

        self::assertSame(0, $this->db->table('banks')->countAllResults());
    }

    public function testDatabaseProviderFindsSeededRowByBankCode(): void
    {
        $this->hasInDatabase('banks', self::SEEDED_ROW);

        $provider = new DatabaseProvider(new BankModel());

        $info = $provider->findByBankCode('ES', '2100', '0418');

        self::assertInstanceOf(BankInfo::class, $info);
        self::assertSame('CaixaBank', $info->bankName);
        self::assertSame('CX', $info->shortName);
        self::assertSame('CAIXESBBXXX', $info->bic);
        self::assertSame('Barcelona', $info->city);
        self::assertNull($info->address);
        self::assertTrue($info->sepaSct);
        self::assertFalse($info->sepaSctInst);
        self::assertTrue($info->sepaSddCore);
        self::assertNull($info->sepaSddB2b);
        self::assertSame('test-fixture', $info->sourceId);
        self::assertSame('2026-07-10', $info->sourceVersion);
        self::assertSame('ODbL', $info->sourceLicense);
        self::assertSame('database', $info->resolvedBy);
    }

    public function testDatabaseProviderReturnsNullWhenRowDoesNotExist(): void
    {
        $provider = new DatabaseProvider(new BankModel());

        self::assertNull($provider->findByBankCode('ES', '9999'));
    }

    public function testDatabaseProviderFindByIbanDelegatesToFindByBankCode(): void
    {
        $this->hasInDatabase('banks', self::SEEDED_ROW);

        $parser = new Parser(new Validator(new Registry()), new Normalizer());
        $parsed = $parser->parse('ES9121000418450200051332');

        $provider = new DatabaseProvider(new BankModel());

        $info = $provider->findByIban($parsed);

        self::assertInstanceOf(BankInfo::class, $info);
        self::assertSame('CaixaBank', $info->bankName);
    }

    public function testResolverWithDatabaseProviderResolvesRealIbanAsResolved(): void
    {
        $this->hasInDatabase('banks', self::SEEDED_ROW);

        $parser   = new Parser(new Validator(new Registry()), new Normalizer());
        $resolver = new Resolver($parser, new DatabaseProvider(new BankModel()));

        $result = $resolver->resolve('ES9121000418450200051332');

        self::assertTrue($result->isResolved());
        self::assertSame('CaixaBank', $result->bankName);
        self::assertSame('CAIXESBBXXX', $result->bic);
        self::assertSame('database', $result->resolvedBy);
    }

    public function testResolverWithDatabaseProviderLeavesUnseededIbanUnresolved(): void
    {
        $parser   = new Parser(new Validator(new Registry()), new Normalizer());
        $resolver = new Resolver($parser, new DatabaseProvider(new BankModel()));

        // No row seeded for this bank/branch combination.
        $result = $resolver->resolve('ES9121000418450200051332');

        self::assertFalse($result->isResolved());
        self::assertNull($result->bankName);
    }

    // -- findByBic (BIC8 matching) -----------------------------------------

    public function testDatabaseProviderFindsSeededRowByFullBicAndSetsResolvedByDatabase(): void
    {
        $this->hasInDatabase('banks', self::SEEDED_ROW);

        $provider = new DatabaseProvider(new BankModel());

        $info = $provider->findByBic('CAIXESBBXXX');

        self::assertInstanceOf(BankInfo::class, $info);
        self::assertSame('CaixaBank', $info->bankName);
        self::assertSame('CAIXESBBXXX', $info->bic);
        self::assertSame('Barcelona', $info->city);
        self::assertSame('database', $info->resolvedBy);
    }

    public function testFindByBicMatchesAnElevenCharRowFromAnEightCharQuery(): void
    {
        // Query is the 8-char BIC8; the stored row is the 11-char BIC.
        $this->hasInDatabase('banks', self::SEEDED_ROW);

        $provider = new DatabaseProvider(new BankModel());

        $info = $provider->findByBic('CAIXESBB');

        self::assertInstanceOf(BankInfo::class, $info);
        self::assertSame('CaixaBank', $info->bankName);
    }

    public function testFindByBicMatchesAnEightCharRowFromAnElevenCharQuery(): void
    {
        // Query is the 11-char BIC; the stored row is the 8-char BIC8.
        $this->hasInDatabase('banks', self::SEEDED_BIC8_ROW);

        $provider = new DatabaseProvider(new BankModel());

        $info = $provider->findByBic('DEUTDEFF500');

        self::assertInstanceOf(BankInfo::class, $info);
        self::assertSame('Deutsche Bank', $info->bankName);
        self::assertSame('DEUTDEFF', $info->bic);
    }

    public function testFindByBicIsCaseInsensitiveAndWhitespaceTolerant(): void
    {
        $this->hasInDatabase('banks', self::SEEDED_ROW);

        $provider = new DatabaseProvider(new BankModel());

        $info = $provider->findByBic(' caix esbb xxx ');

        self::assertInstanceOf(BankInfo::class, $info);
        self::assertSame('CaixaBank', $info->bankName);
    }

    public function testFindByBicReturnsNullWhenNoRowMatches(): void
    {
        $this->hasInDatabase('banks', self::SEEDED_ROW);

        $provider = new DatabaseProvider(new BankModel());

        self::assertNull($provider->findByBic('NWBKGB2L'));
    }

    /**
     * End-to-end through the public facade with a real DB provider: a stored
     * `banks` row resolves by BIC through the whole stack (facade →
     * `Resolver::resolveBic()` → `DatabaseProvider::findByBic()` →
     * `BankModel::findByBic()` → the indexed `bic` column).
     */
    public function testFacadeResolveBicResolvesARealRowThroughTheWholeStack(): void
    {
        $this->hasInDatabase('banks', self::SEEDED_ROW);

        $iban = new Iban(provider: new DatabaseProvider(new BankModel()));

        $info = $iban->resolveBic('CAIXESBBXXX');

        self::assertInstanceOf(BankInfo::class, $info);
        self::assertSame('CaixaBank', $info->bankName);
        self::assertSame('CAIXESBBXXX', $info->bic);
        self::assertSame('database', $info->resolvedBy);
    }

    public function testFacadeResolveBicReturnsNullForAMalformedBicWithoutHittingTheDb(): void
    {
        $this->hasInDatabase('banks', self::SEEDED_ROW);

        $iban = new Iban(provider: new DatabaseProvider(new BankModel()));

        self::assertNull($iban->resolveBic('not-a-bic'));
    }

    /**
     * Functional, query-level proof of the Fix 1 wiring: a `BankModel`
     * constructed with a custom table name genuinely queries THAT table
     * instead of `banks`, end-to-end through `DatabaseProvider` against a
     * real (SQLite `:memory:`) connection.
     *
     * The table is built via `Forge` (like `CreateBanksTable` itself),
     * rather than a raw `CREATE TABLE` query, so it goes through the same
     * `DBPrefix` handling (`'tests'` uses `'db_'`, see the bundled
     * `Config\Database::$tests`) as everything the query builder / Model
     * layer touches -- a raw, unprefixed `CREATE TABLE` would silently
     * create a table the builder can never see.
     *
     * The row is seeded into that hand-created `custom_banks` table (not
     * via the package's `CreateBanksTable` migration, which only ever
     * targets `banks`), and the default `banks` table -- created empty by
     * this class's migration -- is asserted to NOT contain it, proving the
     * lookup below genuinely depends on the overridden table name rather
     * than on `banks` coincidentally having the row too.
     */
    public function testDatabaseProviderQueriesTheConfiguredCustomTableInsteadOfBanks(): void
    {
        $customTable = 'custom_banks';
        $forge       = DatabaseConfig::forge($this->DBGroup);

        $forge->addField('id');
        $forge->addField([
            'country_code'   => ['type' => 'CHAR', 'constraint' => 2, 'null' => false],
            'bank_code'      => ['type' => 'VARCHAR', 'constraint' => 35, 'null' => false],
            'branch_code'    => ['type' => 'VARCHAR', 'constraint' => 35, 'null' => true],
            'bic'            => ['type' => 'VARCHAR', 'constraint' => 11, 'null' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'short_name'     => ['type' => 'VARCHAR', 'constraint' => 140, 'null' => true],
            'city'           => ['type' => 'VARCHAR', 'constraint' => 140, 'null' => true],
            'address'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'sepa_sct'       => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'sepa_sct_inst'  => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'sepa_sdd_core'  => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'sepa_sdd_b2b'   => ['type' => 'TINYINT', 'constraint' => 1, 'null' => true],
            'source_id'      => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'source_version' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'source_license' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $forge->createTable($customTable, true);

        try {
            $this->db->table($customTable)->insert(self::SEEDED_ROW);

            $defaultTableProvider = new DatabaseProvider(new BankModel());
            self::assertNull(
                $defaultTableProvider->findByBankCode('ES', '2100', '0418'),
                'The default banks table must stay empty in this test.',
            );

            $customTableProvider = new DatabaseProvider(new BankModel($customTable));
            $info                = $customTableProvider->findByBankCode('ES', '2100', '0418');

            self::assertInstanceOf(BankInfo::class, $info);
            self::assertSame('CaixaBank', $info->bankName);
            self::assertSame('CAIXESBBXXX', $info->bic);
        } finally {
            $forge->dropTable($customTable, true);
        }
    }
}
