<?php

declare(strict_types=1);

namespace Tests\Import;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\ImportReport;
use Daycry\Iban\Import\ImportRunner;
use Daycry\Iban\Models\BankModel;
use Tests\_support\FakeAtImporter;

/**
 * End-to-end DB test for `ImportRunner` (V-6): runs the real
 * `CreateBanksTable` migration against a SQLite `:memory:` database (same
 * setup as `tests/Database/DatabaseProviderTest.php`), then proves the fake
 * test importer's rows actually land in `banks` with provenance, that
 * `--dry-run` writes nothing, and that re-running upserts instead of
 * duplicating.
 *
 * @see \Daycry\Iban\Import\ImportRunner
 * @see \Tests\_support\FakeAtImporter
 */
final class ImportRunnerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'Daycry\Iban';
    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;

    public function testRunInsertsFixtureRowsWithProvenance(): void
    {
        $report = (new ImportRunner())->run(new FakeAtImporter(), new BankModel());

        self::assertInstanceOf(ImportReport::class, $report);
        self::assertSame('AT', $report->countryCode);
        self::assertSame('fake', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);
        self::assertFalse($report->dryRun);
        self::assertSame([], $report->messages);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'AT',
            'bank_code'      => '20111',
            'name'           => 'Erste Bank',
            'bic'            => 'GIBAATWWXXX',
            'source_id'      => 'fake',
            'source_license' => 'Public Domain (test fixture)',
        ]);

        $result = $this->db->table('banks')
            ->where('bank_code', '20111')
            ->get();

        self::assertNotFalse($result);

        $row = $result->getRowArray();

        self::assertIsArray($row);
        self::assertNull($row['branch_code'], 'A row without a branch_code must be stored as NULL, not an empty string.');
        self::assertNotEmpty($row['source_version']);
        self::assertNotEmpty($row['updated_at']);
    }

    public function testDryRunWritesNothingButStillCountsTheReport(): void
    {
        $report = (new ImportRunner())->run(new FakeAtImporter(), new BankModel(), dryRun: true);

        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);
        self::assertTrue($report->dryRun);

        self::assertSame(0, $this->db->table('banks')->countAllResults(), 'A dry-run must not write any row.');
    }

    public function testRerunningTheSameImporterUpsertsInsteadOfDuplicating(): void
    {
        $runner = new ImportRunner();

        $firstReport = $runner->run(new FakeAtImporter(), new BankModel());
        self::assertSame(3, $firstReport->imported);
        self::assertSame(3, $this->db->table('banks')->countAllResults());

        // Same natural keys, but one row's `name` changed -- proves the
        // second run updates the existing row instead of inserting a
        // duplicate.
        $changedRows   = FakeAtImporter::defaultRows();
        $changedRows[1]['name'] = 'Erste Group Bank AG';

        $secondReport = $runner->run(new FakeAtImporter($changedRows), new BankModel());

        self::assertSame(3, $secondReport->imported);
        self::assertSame(3, $this->db->table('banks')->countAllResults(), 'Re-running must upsert, not duplicate rows.');

        $this->seeInDatabase('banks', [
            'country_code' => 'AT',
            'bank_code'    => '20111',
            'name'         => 'Erste Group Bank AG',
        ]);

        $this->dontSeeInDatabase('banks', [
            'country_code' => 'AT',
            'bank_code'    => '20111',
            'name'         => 'Erste Bank',
        ]);
    }

    public function testNullAndNonNullBranchCodeForTheSameBankCodeAreDistinctRowsAndBothUpsertCleanly(): void
    {
        $runner = new ImportRunner();

        $rows = [
            ['bank_code' => '99999', 'branch_code' => null, 'name' => 'HQ'],
            ['bank_code' => '99999', 'branch_code' => '00001', 'name' => 'Branch'],
        ];

        $runner->run(new FakeAtImporter($rows), new BankModel());

        self::assertSame(2, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', ['bank_code' => '99999', 'branch_code' => null, 'name' => 'HQ']);
        $this->seeInDatabase('banks', ['bank_code' => '99999', 'branch_code' => '00001', 'name' => 'Branch']);

        // Re-run with the same two natural keys (HQ renamed): still 2 rows.
        $rows[0]['name'] = 'Headquarters';

        $runner->run(new FakeAtImporter($rows), new BankModel());

        self::assertSame(2, $this->db->table('banks')->countAllResults(), 'Upsert must match NULL branch_code by IS NULL, not create a duplicate.');
        $this->seeInDatabase('banks', ['bank_code' => '99999', 'branch_code' => null, 'name' => 'Headquarters']);
    }

    public function testRowsMissingARequiredBankCodeAreSkippedAndNotWritten(): void
    {
        $importer = new class () implements ImporterInterface {
            public function countryCode(): string
            {
                return 'AT';
            }

            public function sourceId(): string
            {
                return 'broken-fake';
            }

            public function sourceName(): string
            {
                return 'Broken Fake Importer';
            }

            public function license(): string
            {
                return 'n/a';
            }

            public function sourceUrl(): string
            {
                return 'https://example.test/broken-fake-importer';
            }

            public function rows(?string $localFile = null): iterable
            {
                yield ['bank_code' => '11111', 'name' => 'Valid Row'];
                yield ['name' => 'Missing bank_code'];
                yield ['bank_code' => '', 'name' => 'Empty bank_code'];
            }
        };

        $report = (new ImportRunner())->run($importer, new BankModel());

        self::assertSame(3, $report->fetched);
        self::assertSame(1, $report->imported);
        self::assertSame(2, $report->skipped);
        self::assertCount(2, $report->messages);

        self::assertSame(1, $this->db->table('banks')->countAllResults());
    }
}
