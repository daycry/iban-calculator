<?php

declare(strict_types=1);

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Iban\Core\Normalizer;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\Database\Seeds\BanksSeeder;
use Daycry\Iban\DTO\BankInfo;
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
}
