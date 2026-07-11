<?php

declare(strict_types=1);

namespace Tests\Import;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Iban\Import\Importers\BancoDeEspanaImporter;
use Daycry\Iban\Import\Importers\BetaalverenigingImporter;
use Daycry\Iban\Import\Importers\BundesbankImporter;
use Daycry\Iban\Import\Importers\OenbImporter;
use Daycry\Iban\Import\Importers\SixImporter;
use Daycry\Iban\Import\ImportReport;
use Daycry\Iban\Import\ImportRunner;
use Daycry\Iban\Models\BankModel;

/**
 * End-to-end DB test: runs the real `OenbImporter` (AT) / `BundesbankImporter`
 * (DE) (V-7a), plus `SixImporter` (CH) / `BetaalverenigingImporter` (NL) /
 * `BancoDeEspanaImporter` (ES) (V-7b) -- reading their hand-crafted,
 * format-accurate fixtures under `tests/Fixtures/import/` -- through the
 * real `ImportRunner` against a SQLite `:memory:` `banks` table (same setup
 * as `tests/Import/ImportRunnerTest.php`, which proves the same plumbing
 * with a fake importer).
 *
 * @see \Daycry\Iban\Import\Importers\OenbImporter
 * @see \Daycry\Iban\Import\Importers\BundesbankImporter
 * @see \Daycry\Iban\Import\Importers\SixImporter
 * @see \Daycry\Iban\Import\Importers\BetaalverenigingImporter
 * @see \Daycry\Iban\Import\Importers\BancoDeEspanaImporter
 * @see \Daycry\Iban\Import\ImportRunner
 */
final class ImportRunnerImportersTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'Daycry\Iban';
    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;

    private const OENB_FIXTURE             = __DIR__ . '/../Fixtures/import/oenb_sample.csv';
    private const BUNDESBANK_FIXTURE       = __DIR__ . '/../Fixtures/import/bundesbank_sample.txt';
    private const SIX_FIXTURE              = __DIR__ . '/../Fixtures/import/six_sample.csv';
    private const BETAALVERENIGING_FIXTURE = __DIR__ . '/../Fixtures/import/betaalvereniging_sample.csv';
    private const BDE_FIXTURE              = __DIR__ . '/../Fixtures/import/bde_sample.csv';

    public function testOenbImporterImportsHeadOfficeRowsWithProvenance(): void
    {
        $report = (new ImportRunner())->run(new OenbImporter(), new BankModel(), false, self::OENB_FIXTURE);

        self::assertInstanceOf(ImportReport::class, $report);
        self::assertSame('AT', $report->countryCode);
        self::assertSame('oenb', $report->sourceId);
        self::assertSame(2, $report->fetched);
        self::assertSame(2, $report->imported);
        self::assertSame(0, $report->skipped);
        self::assertFalse($report->dryRun);

        self::assertSame(2, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'AT',
            'bank_code'      => '12000',
            'branch_code'    => null,
            'name'           => 'Bank Austria',
            'city'           => 'Wien',
            'address'        => 'Rothschildplatz 1, 1020 Wien',
            'source_id'      => 'oenb',
            'source_license' => 'CC-BY-4.0 (OeNB)',
        ]);

        // The branch ("Zweigstelle") row for the same BLZ must NOT have
        // overwritten/duplicated the head-office row.
        $this->dontSeeInDatabase('banks', [
            'bank_code' => '12000',
            'address'   => 'Praterstraße 15, 1020 Wien',
        ]);

        $result = $this->db->table('banks')->where('bank_code', '12000')->get();
        self::assertNotFalse($result);
        $row = $result->getRowArray();
        self::assertIsArray($row);
        self::assertNotEmpty($row['source_version']);
        self::assertNotEmpty($row['updated_at']);
    }

    public function testBundesbankImporterImportsPrincipalRowsWithProvenance(): void
    {
        $report = (new ImportRunner())->run(new BundesbankImporter(), new BankModel(), false, self::BUNDESBANK_FIXTURE);

        self::assertSame('DE', $report->countryCode);
        self::assertSame('bundesbank', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'DE',
            'bank_code'      => '37040044',
            'branch_code'    => null,
            'name'           => 'Commerzbank',
            'short_name'     => 'Commerzbank Essen',
            'city'           => 'Essen',
            'bic'            => 'COBADEFFXXX',
            'source_id'      => 'bundesbank',
            'source_license' => 'Deutsche Bundesbank',
        ]);

        // The Merkmal '2' (subordinate) record for the same BLZ (Koeln) must
        // NOT have overwritten the Merkmal '1' (principal, Essen) row.
        $this->dontSeeInDatabase('banks', [
            'bank_code' => '37040044',
            'city'      => 'Koeln',
        ]);

        $this->seeInDatabase('banks', [
            'country_code' => 'DE',
            'bank_code'    => '50010517',
            'name'         => 'ING-DiBa',
            'bic'          => 'INGDDEFFXXX',
        ]);

        // ISO-8859-1 -> UTF-8 round trip: the raw Latin-1 "ü" byte (0xFC) in
        // the fixture's 4th record must have been decoded to a valid UTF-8
        // "Lübben" by the time it's persisted, not mojibake or raw bytes.
        $this->seeInDatabase('banks', [
            'country_code' => 'DE',
            'bank_code'    => '16050202',
            'name'         => 'Sparkasse Niederlausitz',
            'short_name'   => 'Spk Niederlausitz Lübben',
            'city'         => 'Lübben',
        ]);
    }

    public function testSixImporterImportsRowsWithProvenanceAndZeroPadsTheBankCode(): void
    {
        $report = (new ImportRunner())->run(new SixImporter(), new BankModel(), false, self::SIX_FIXTURE);

        self::assertSame('CH', $report->countryCode);
        self::assertSame('six', $report->sourceId);
        self::assertSame(2, $report->fetched);
        self::assertSame(2, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(2, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'CH',
            'bank_code'      => '00700', // '700' left-padded to 5 digits
            'branch_code'    => null,
            'name'           => 'Zürcher Kantonalbank', // UTF-8 round trip
            'city'           => 'Zürich',
            'bic'            => 'ZKBKCHZZ80A',
            'source_id'      => 'six',
            'source_license' => 'SIX Interbank Clearing (free use)',
        ]);

        // The Concatenation='Y' merger stub (IID 4835) must not have been
        // imported at all.
        $this->dontSeeInDatabase('banks', ['bank_code' => '04835']);
        $this->dontSeeInDatabase('banks', ['bank_code' => '00230']);

        $this->seeInDatabase('banks', [
            'country_code' => 'CH',
            'bank_code'    => '09000', // '9000' left-padded to 5 digits
            'name'         => 'PostFinance AG',
            'bic'          => 'POFICHBEXXX',
        ]);
    }

    public function testBetaalverenigingImporterImportsRowsWithProvenanceSkippingThePreamble(): void
    {
        $report = (new ImportRunner())->run(new BetaalverenigingImporter(), new BankModel(), false, self::BETAALVERENIGING_FIXTURE);

        self::assertSame('NL', $report->countryCode);
        self::assertSame('betaalvereniging', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'NL',
            'bank_code'      => 'ABNA',
            'branch_code'    => null,
            'bic'            => 'ABNANL2A',
            'name'           => 'ABN AMRO BANK N.V.',
            'source_id'      => 'betaalvereniging',
            'source_license' => 'Betaalvereniging Nederland (see terms)',
        ]);

        // The title row and the header row must not have produced rows.
        $this->dontSeeInDatabase('banks', ['bank_code' => 'Identifier']);
    }

    public function testBancoDeEspanaImporterImportsRowsWithProvenanceSkippingMoneyMarketFunds(): void
    {
        $report = (new ImportRunner())->run(new BancoDeEspanaImporter(), new BankModel(), false, self::BDE_FIXTURE);

        self::assertSame('ES', $report->countryCode);
        self::assertSame('bde', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'ES',
            'bank_code'      => '0049', // zero-led code kept as a STRING
            'branch_code'    => null,
            'name'           => 'Banco Santander, S.A.', // comma-in-quotes preserved
            'address'        => 'Ps de Pereda, 9-12, 39004, Santander',
            'source_id'      => 'bde',
            'source_license' => 'Banco de España',
        ]);

        // The FI2680 money-market-fund row must not have been imported.
        $this->dontSeeInDatabase('banks', ['bank_code' => 'FI2680']);

        $this->seeInDatabase('banks', [
            'country_code' => 'ES',
            'bank_code'    => '0182',
            'name'         => 'Banco Bilbao Vizcaya Argentaria, S.A.', // UTF-8 accented char round trip
        ]);
    }

    public function testBothImportersCanCoexistInTheSameBanksTable(): void
    {
        $runner = new ImportRunner();

        $runner->run(new OenbImporter(), new BankModel(), false, self::OENB_FIXTURE);
        $runner->run(new BundesbankImporter(), new BankModel(), false, self::BUNDESBANK_FIXTURE);

        self::assertSame(5, $this->db->table('banks')->countAllResults());

        $atModel = (new BankModel())->findByNaturalKey('AT', '20111', null);
        self::assertIsArray($atModel);
        self::assertSame('Erste Bank der oesterreichischen Sparkassen AG', $atModel['name']);

        $deModel = (new BankModel())->findByNaturalKey('DE', '50010517', null);
        self::assertIsArray($deModel);
        self::assertSame('ING-DiBa', $deModel['name']);
    }

    public function testAllFiveImportersCanCoexistInTheSameBanksTable(): void
    {
        $runner = new ImportRunner();
        $model  = new BankModel();

        $runner->run(new OenbImporter(), $model, false, self::OENB_FIXTURE);
        $runner->run(new BundesbankImporter(), $model, false, self::BUNDESBANK_FIXTURE);
        $runner->run(new SixImporter(), $model, false, self::SIX_FIXTURE);
        $runner->run(new BetaalverenigingImporter(), $model, false, self::BETAALVERENIGING_FIXTURE);
        $runner->run(new BancoDeEspanaImporter(), $model, false, self::BDE_FIXTURE);

        // 2 (AT) + 3 (DE) + 2 (CH) + 3 (NL) + 3 (ES) = 13.
        self::assertSame(13, $this->db->table('banks')->countAllResults());

        $chModel = (new BankModel())->findByNaturalKey('CH', '09000', null);
        self::assertIsArray($chModel);
        self::assertSame('PostFinance AG', $chModel['name']);

        $nlModel = (new BankModel())->findByNaturalKey('NL', 'RABO', null);
        self::assertIsArray($nlModel);
        self::assertSame('RABOBANK', $nlModel['name']);

        $esModel = (new BankModel())->findByNaturalKey('ES', '2100', null);
        self::assertIsArray($esModel);
        self::assertSame('Caixabank, S.A.', $esModel['name']);
    }
}
