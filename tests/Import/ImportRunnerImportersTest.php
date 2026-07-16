<?php

declare(strict_types=1);

namespace Tests\Import;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Daycry\Iban\Iban;
use Daycry\Iban\Import\Importers\BancoDeEspanaImporter;
use Daycry\Iban\Import\Importers\BankOfIsraelImporter;
use Daycry\Iban\Import\Importers\BankOfSloveniaImporter;
use Daycry\Iban\Import\Importers\BetaalverenigingImporter;
use Daycry\Iban\Import\Importers\BitsNorwayImporter;
use Daycry\Iban\Import\Importers\BrazilianCentralBankImporter;
use Daycry\Iban\Import\Importers\BulgarianNationalBankImporter;
use Daycry\Iban\Import\Importers\BundesbankImporter;
use Daycry\Iban\Import\Importers\CentralBankOfAzerbaijanImporter;
use Daycry\Iban\Import\Importers\CentralBankOfMaltaImporter;
use Daycry\Iban\Import\Importers\CroatianNationalBankImporter;
use Daycry\Iban\Import\Importers\CzechNationalBankImporter;
use Daycry\Iban\Import\Importers\EpcRegisterImporter;
use Daycry\Iban\Import\Importers\EstonianBankingAssociationImporter;
use Daycry\Iban\Import\Importers\HellenicBankAssociationImporter;
use Daycry\Iban\Import\Importers\LiechtensteinImporter;
use Daycry\Iban\Import\Importers\LuxembourgBankersAssociationImporter;
use Daycry\Iban\Import\Importers\MagyarNemzetiBankImporter;
use Daycry\Iban\Import\Importers\NationalBankOfBelgiumImporter;
use Daycry\Iban\Import\Importers\NationalBankOfGeorgiaImporter;
use Daycry\Iban\Import\Importers\NationalBankOfKazakhstanImporter;
use Daycry\Iban\Import\Importers\NationalBankOfMoldovaImporter;
use Daycry\Iban\Import\Importers\NationalBankOfPolandImporter;
use Daycry\Iban\Import\Importers\NationalBankOfSlovakiaImporter;
use Daycry\Iban\Import\Importers\NationalBankOfUkraineImporter;
use Daycry\Iban\Import\Importers\OenbImporter;
use Daycry\Iban\Import\Importers\RegafiImporter;
use Daycry\Iban\Import\Importers\SixImporter;
use Daycry\Iban\Import\Importers\SwedenBankInfrastructureImporter;
use Daycry\Iban\Import\ImportReport;
use Daycry\Iban\Import\ImportRunner;
use Daycry\Iban\Models\BankModel;
use Daycry\Iban\Providers\DatabaseProvider;
use Tests\_support\XlsxFixtureFactory;

/**
 * End-to-end DB test: runs the real `OenbImporter` (AT) / `BundesbankImporter`
 * (DE) (V-7a), plus `SixImporter` (CH) / `BetaalverenigingImporter` (NL) /
 * `BancoDeEspanaImporter` (ES) (V-7b), plus `CzechNationalBankImporter` (CZ) /
 * `HellenicBankAssociationImporter` (GR) / `BankOfSloveniaImporter` (SI) /
 * `NationalBankOfSlovakiaImporter` (SK) (v1.2), plus
 * `BulgarianNationalBankImporter` (BG) / `NationalBankOfMoldovaImporter` (MD) /
 * `NationalBankOfPolandImporter` (PL) / `CentralBankOfAzerbaijanImporter` (AZ)
 * (v1.2 follow-up, XML sources) -- reading their hand-crafted,
 * format-accurate fixtures under `tests/Fixtures/import/` -- through the
 * real `ImportRunner` against a SQLite `:memory:` `banks` table (same setup
 * as `tests/Import/ImportRunnerTest.php`, which proves the same plumbing
 * with a fake importer).
 *
 * The v1.2 additions also prove the Resolver's bank-level fallback
 * end-to-end: `Resolver::resolve()` tries `findByIban()` (exact branch
 * match) first and falls back to `findByBankCode(cc, bank, null)` when it
 * misses -- so a bank-level-only seeded row (`branch_code IS NULL`, one row
 * per institution, as every importer in this package seeds) still resolves
 * any IBAN of that bank, even for countries whose IBAN carries a branch
 * segment (e.g. GR).
 *
 * This v1.2 BE/HR/LU/MT batch adds four more, XLSX-sourced importers --
 * their fixtures are generated on the fly with {@see XlsxFixtureFactory}
 * (in {@see self::setUp()}) rather than committed as binary files, unlike
 * every CSV/XML fixture above. This v1.2 HU/NO/GE batch adds three more,
 * also XLSX-sourced and fixture-generated the same way. This v1.2 IL/UA/KZ
 * batch adds three more, JSON-sourced importers -- `BankOfIsraelImporter`
 * (IL), `NationalBankOfUkraineImporter` (UA) and
 * `NationalBankOfKazakhstanImporter` (KZ) -- reading committed JSON fixtures
 * under `tests/Fixtures/import/`, same as the CSV/XML importers above.
 *
 * This v1.2 final BR/LI batch adds two more -- `BrazilianCentralBankImporter`
 * (BR, comma CSV) and `LiechtensteinImporter` (LI, which reuses `SixImporter`'s
 * shared SIX Bank Master V3 fixture, filtered to `Country=LI` instead of
 * `Country=CH`). It also proves the `SixImporter` country-filtering bugfix
 * that shipped alongside them: the shared fixture's `Country=LI` row must
 * never leak into a CH-scoped import (see
 * `testSixImporterImportDoesNotLeakTheLiechtensteinBankAsACHRow()`).
 *
 * @see \Daycry\Iban\Import\Importers\OenbImporter
 * @see \Daycry\Iban\Import\Importers\BundesbankImporter
 * @see \Daycry\Iban\Import\Importers\SixImporter
 * @see \Daycry\Iban\Import\Importers\BetaalverenigingImporter
 * @see \Daycry\Iban\Import\Importers\BancoDeEspanaImporter
 * @see \Daycry\Iban\Import\Importers\CzechNationalBankImporter
 * @see \Daycry\Iban\Import\Importers\HellenicBankAssociationImporter
 * @see \Daycry\Iban\Import\Importers\BankOfSloveniaImporter
 * @see \Daycry\Iban\Import\Importers\NationalBankOfSlovakiaImporter
 * @see \Daycry\Iban\Import\Importers\BulgarianNationalBankImporter
 * @see \Daycry\Iban\Import\Importers\NationalBankOfMoldovaImporter
 * @see \Daycry\Iban\Import\Importers\NationalBankOfPolandImporter
 * @see \Daycry\Iban\Import\Importers\CentralBankOfAzerbaijanImporter
 * @see \Daycry\Iban\Import\Importers\NationalBankOfBelgiumImporter
 * @see \Daycry\Iban\Import\Importers\CroatianNationalBankImporter
 * @see \Daycry\Iban\Import\Importers\LuxembourgBankersAssociationImporter
 * @see \Daycry\Iban\Import\Importers\CentralBankOfMaltaImporter
 * @see \Daycry\Iban\Import\Importers\MagyarNemzetiBankImporter
 * @see \Daycry\Iban\Import\Importers\BitsNorwayImporter
 * @see \Daycry\Iban\Import\Importers\NationalBankOfGeorgiaImporter
 * @see \Daycry\Iban\Import\Importers\BankOfIsraelImporter
 * @see \Daycry\Iban\Import\Importers\NationalBankOfUkraineImporter
 * @see \Daycry\Iban\Import\Importers\NationalBankOfKazakhstanImporter
 * @see \Daycry\Iban\Import\Importers\LiechtensteinImporter
 * @see \Daycry\Iban\Import\Importers\BrazilianCentralBankImporter
 * @see \Daycry\Iban\Import\ImportRunner
 * @see \Daycry\Iban\Resolver\Resolver
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
    private const CNB_FIXTURE              = __DIR__ . '/../Fixtures/import/cnb_sample.csv';
    private const HBA_FIXTURE              = __DIR__ . '/../Fixtures/import/hba_sample.csv';
    private const BSI_FIXTURE              = __DIR__ . '/../Fixtures/import/bsi_sample.csv';
    private const NBS_FIXTURE              = __DIR__ . '/../Fixtures/import/nbs_sample.csv';
    private const BNB_FIXTURE              = __DIR__ . '/../Fixtures/import/bnb_sample.xml';
    private const BNM_FIXTURE              = __DIR__ . '/../Fixtures/import/bnm_sample.xml';
    private const NBP_FIXTURE              = __DIR__ . '/../Fixtures/import/nbp_sample.xml';
    private const CBAR_FIXTURE             = __DIR__ . '/../Fixtures/import/cbar_sample.xml';
    private const BOI_FIXTURE              = __DIR__ . '/../Fixtures/import/boi_sample.json';
    private const NBU_FIXTURE              = __DIR__ . '/../Fixtures/import/nbu_sample.json';
    private const NBK_FIXTURE              = __DIR__ . '/../Fixtures/import/nbk_sample.json';
    private const BCB_FIXTURE              = __DIR__ . '/../Fixtures/import/bcb_sample.csv';
    private const EPC_FIXTURE              = __DIR__ . '/../Fixtures/import/epc_sct_sample.csv';
    private const SE_FIXTURE               = __DIR__ . '/../Fixtures/import/se_sample.psv';
    private const REGAFI_FIXTURE           = __DIR__ . '/../Fixtures/import/regafi_sample.json';
    private const EE_FIXTURE               = __DIR__ . '/../Fixtures/import/ee_sample.html';

    private const CZ_EXAMPLE_IBAN = 'CZ6508000000192000145399';
    private const GR_EXAMPLE_IBAN = 'GR1601101250000000012300695';
    private const SI_EXAMPLE_IBAN = 'SI56263300012039086';
    private const SK_EXAMPLE_IBAN = 'SK3112000000198742637541';
    private const BG_EXAMPLE_IBAN = 'BG80BNBG96611020345678';
    private const MD_EXAMPLE_IBAN = 'MD24AG000225100013104168';
    private const PL_EXAMPLE_IBAN = 'PL61109010140000071219812874';
    private const AZ_EXAMPLE_IBAN = 'AZ21NABZ00000000137010001944';
    private const BE_EXAMPLE_IBAN = 'BE68539007547034';
    private const HR_EXAMPLE_IBAN = 'HR1210010051863000160';
    private const LU_EXAMPLE_IBAN = 'LU280019400644750000';
    private const MT_EXAMPLE_IBAN = 'MT84MALT011000012345MTLCAST001S';
    private const HU_EXAMPLE_IBAN = 'HU42117730161111101800000000';
    private const NO_EXAMPLE_IBAN = 'NO9386011117947';
    private const GE_EXAMPLE_IBAN = 'GE29NB0000000101904917';
    private const IL_EXAMPLE_IBAN = 'IL620108000000099999999';
    private const UA_EXAMPLE_IBAN = 'UA903052992990004149123456789';
    private const KZ_EXAMPLE_IBAN = 'KZ86125KZT5004100100';
    private const LI_EXAMPLE_IBAN = 'LI21088100002324013AA';
    private const BR_EXAMPLE_IBAN = 'BR9700360305000010009795493P1';

    // SEPA-coverage batch example IBANs (MOD-97-valid; bank code as extracted
    // by the structural registry).
    private const SE_EXAMPLE_IBAN = 'SE4550000000058398257466'; // bank code '500' = SEB
    private const FR_EXAMPLE_IBAN = 'FR0530003000001234567890100'; // CIB '30003' = Société Générale
    private const MC_EXAMPLE_IBAN = 'MC3112739000001234567890100'; // CIB '12739' = CFM Indosuez
    private const EE_EXAMPLE_IBAN = 'EE382200221020145685'; // bank code '22' = Swedbank

    // MOD-97-valid test IBANs for the EPC SEPA Register importer's seeded
    // banks. GB's is the SRLG example handed down with the task brief
    // (confirmed valid); IE/RO/LV/GI are built with Mod97::checkDigits()
    // against each seeded bank's real bank_code (see EpcRegisterImporterTest
    // for where these BICs/names come from).
    private const GB_EXAMPLE_IBAN = 'GB31SRLG60837107670802';
    private const IE_EXAMPLE_IBAN = 'IE73AIBK93086212345678';
    private const RO_EXAMPLE_IBAN = 'RO63BTRL0000123456789012';
    private const LV_EXAMPLE_IBAN = 'LV48HABA0000123456789';
    private const GI_EXAMPLE_IBAN = 'GI29XAPO000000012345678';

    private ?string $nbbFixture = null;

    private ?string $hnbFixture = null;

    private ?string $abblFixture = null;

    private ?string $cbmFixture = null;

    private ?string $mnbFixture = null;

    private ?string $bitsFixture = null;

    private ?string $nbgFixture = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->nbbFixture = XlsxFixtureFactory::write([
            ['Version 06/05/2026'],
            [
                'T_Identification_Number',
                'Biccode',
                'T_Institutions_Dutch',
                'T_Institutions_French',
                'T_Institutions_German',
                'T_Institutions_English',
            ],
            ['000', 'GEBA BE BB', 'BNP Paribas Fortis', '', '', ''],
            ['539', 'GEBA BE BB', 'Voorbeeldbank NV (fixture — BE registry example bank code)', '', '', ''],
            ['995', 'N/A', 'VRIJ', 'LIBRE', '', ''],
        ]);

        $this->hnbFixture = XlsxFixtureFactory::write([
            ['', 'Payment service provider codes', '', '', ''],
            ['', '', '', '', ''],
            ['', 'Payment service provider', 'Code', "SWIFT adresa\n(BIC)", ''],
            ['', 'ADDIKO BANK d.d. Zagreb', '2500009', 'HAAB HR 22', ''],
            ['', 'AIRCASH d.o.o. Zagreb', '4501006', '', ''],
            ['', 'HRVATSKA NARODNA BANKA', '1001005', 'NBHR HR 2X ', ''],
        ]);

        $this->abblFixture = XlsxFixtureFactory::write([
            ['ABBL' . "\n\n" . 'List of IBAN and BIC codes of Luxembourg credit institutions' . "\n"],
            ['Credit institution', 'IBAN Code ', ' BIC Code'],
            ["Banque et Caisse d'Epargne de l'Etat, Luxembourg (Spuerkeess)", '001', 'BCEE LU LL'],
            ['Banque Internationale à Luxembourg', '002', 'BILL LU LL'],
            ['Bank Julius Baer Europe S.A.', '032', 'BAERLULU'],
        ]);

        $this->cbmFixture = XlsxFixtureFactory::write([
            ['BIC Code', 'Financial Institution Name', 'National ID (Sort Code)', 'Branch', 'Remarks'],
            ['MALTMTMT', 'Central Bank of Malta', '01100', 'Valletta', ''],
            ['LBMAMTMT', 'Lombard Bank Malta plc.', '05000', 'Head Office', 'Used in all IBANs'],
            ['', '', '05016', 'Valletta', ''],
            ['PYMXMTMTXXX', 'Finance Incorporated Ltd.', '09014', 'Swatar', ''],
            ['PYMXMTMTMAL', 'Finance Incorporated Ltd.', '09025', 'Swatar', ''],
        ]);

        $this->mnbFixture = XlsxFixtureFactory::write([
            [
                'Branch office code',
                'BIC code',
                'Name of the branch office',
                'Address of the branch office',
                'Branch office may send VIBER items',
                'Branch office may receive VIBER items',
            ],
            ['11773011', 'OTPVHUHB', 'OTP Bank Nyrt. (fixture — HU registry example bank code)', '1051 Budapest, Nádor utca 16.', 'S', 'R'],
            ['11773012', 'OTPVHUHB', 'OTP Budapesti r., II. Széna tér', '1015 Budapest, Széna tér 7.', 'S', 'R'],
            ['10002003', 'MANEHUHB', 'Magyar Államkincstár', '1139 Budapest, Váci út 71.', '', 'R'],
        ]);

        $this->bitsFixture = XlsxFixtureFactory::write([
            ['Bank identifier', 'BIC', 'Bank'],
            ['0500', 'DNBANOKK', 'DNB Bank ASA'],
            ['8601', 'DABANO22', 'Danske Bank (fixture — NO registry example bank code)'],
        ]);

        $this->nbgFixture = XlsxFixtureFactory::write([
            ['მონაწილის დასახელება', 'RTGS მონაწილის კოდი', 'IBAN კოდი'],
            ['საქართველოს ეროვნული ბანკი (fixture — GE registry example bank code)', ' BNLNGE22', 'NB'],
            [' სს "საქართველოს ბანკი"', ' BAGAGE22', ' BG'],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach ([
            $this->nbbFixture,
            $this->hnbFixture,
            $this->abblFixture,
            $this->cbmFixture,
            $this->mnbFixture,
            $this->bitsFixture,
            $this->nbgFixture,
        ] as $path) {
            if ($path !== null && is_file($path)) {
                unlink($path);
            }
        }

        $this->nbbFixture  = null;
        $this->hnbFixture  = null;
        $this->abblFixture = null;
        $this->cbmFixture  = null;
        $this->mnbFixture  = null;
        $this->bitsFixture = null;
        $this->nbgFixture  = null;
    }

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

    /**
     * Regression test for the latent country-filtering bug fixed alongside
     * {@see LiechtensteinImporter}'s introduction: the shared SIX Bank
     * Master V3 fixture also carries a `Country=LI` row (IID 8810,
     * Liechtensteinische Landesbank AG) -- importing it via `SixImporter`
     * must NOT leak that row in as a CH bank, so resolving its own LI IBAN
     * against a CH-only import must fail to resolve (and certainly must
     * never resolve to the LI bank's name).
     */
    public function testSixImporterImportDoesNotLeakTheLiechtensteinBankAsACHRow(): void
    {
        (new ImportRunner())->run(new SixImporter(), new BankModel(), false, self::SIX_FIXTURE);

        $this->dontSeeInDatabase('banks', ['bank_code' => '08810']);
        $this->dontSeeInDatabase('banks', ['name' => 'Liechtensteinische Landesbank AG']);

        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::LI_EXAMPLE_IBAN);

        self::assertFalse($result->isResolved());
    }

    public function testLiechtensteinImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new LiechtensteinImporter(), new BankModel(), false, self::SIX_FIXTURE);

        self::assertSame('LI', $report->countryCode);
        self::assertSame('six', $report->sourceId);
        self::assertSame(1, $report->fetched);
        self::assertSame(1, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(1, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'LI',
            'bank_code'      => '08810', // '8810' left-padded to 5 digits
            'branch_code'    => null,
            'name'           => 'Liechtensteinische Landesbank AG',
            'city'           => 'Vaduz',
            'bic'            => 'LILALI2XXXX',
            'source_id'      => 'six',
            'source_license' => 'SIX Interbank Clearing (free use)',
        ]);

        // The CH rows/merger stub in the same shared fixture must not have
        // been imported by this LI-scoped run.
        $this->dontSeeInDatabase('banks', ['bank_code' => '00700']);
        $this->dontSeeInDatabase('banks', ['bank_code' => '09000']);

        // The real proof: resolving the SIX registry's own LI example IBAN
        // (bank code '08810') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::LI_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Liechtensteinische Landesbank AG', $result->bankName);
    }

    public function testBrazilianCentralBankImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new BrazilianCentralBankImporter(), new BankModel(), false, self::BCB_FIXTURE);

        self::assertSame('BR', $report->countryCode);
        self::assertSame('bcb', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'BR',
            'bank_code'      => '00360305',
            'branch_code'    => null,
            'name'           => 'CAIXA ECONOMICA FEDERAL',
            'source_id'      => 'bcb',
            'source_license' => 'Banco Central do Brasil (ODbL)',
        ]);

        $this->seeInDatabase('banks', [
            'country_code' => 'BR',
            'bank_code'    => '00000000',
            'name'         => 'Banco do Brasil S.A.',
        ]);

        // The real proof: resolving BCB's own example IBAN (ISPB bank code
        // '00360305', WITH a branch segment the bank-level row has no exact
        // match for) against the seeded bank-level row, via the Resolver's
        // findByBankCode(cc, bank, null) fallback.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::BR_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('CAIXA ECONOMICA FEDERAL', $result->bankName);
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

    public function testCzechNationalBankImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new CzechNationalBankImporter(), new BankModel(), false, self::CNB_FIXTURE);

        self::assertSame('CZ', $report->countryCode);
        self::assertSame('cnb', $report->sourceId);
        self::assertSame(8, $report->fetched);
        self::assertSame(8, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(8, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'CZ',
            'bank_code'      => '0800',
            'branch_code'    => null,
            'name'           => 'Česká spořitelna, a.s.', // UTF-8 accented char round trip
            'bic'            => 'GIBACZPX',
            'source_id'      => 'cnb',
            'source_license' => 'Czech National Bank (cite source, no changes)',
        ]);

        // The header row must not have produced a row.
        $this->dontSeeInDatabase('banks', ['name' => 'Poskytovatel platebních služeb']);

        // The real proof: resolving the CZ registry's own example IBAN
        // (bank code 0800) against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::CZ_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Česká spořitelna, a.s.', $result->bankName);
    }

    public function testHellenicBankAssociationImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new HellenicBankAssociationImporter(), new BankModel(), false, self::HBA_FIXTURE);

        self::assertSame('GR', $report->countryCode);
        self::assertSame('hba', $report->sourceId);
        self::assertSame(5, $report->fetched);
        self::assertSame(5, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(5, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'GR',
            'bank_code'      => '011',
            'branch_code'    => null,
            'name'           => 'NATIONAL BANK OF GREECE S.A.',
            'source_id'      => 'hba',
            'source_license' => 'Hellenic Bank Association (HEBIC)',
        ]);

        // The title/header preamble rows must not have produced rows.
        $this->dontSeeInDatabase('banks', ['name' => 'Code Number']);

        // The real proof: resolving the GR registry's own example IBAN
        // (bank code 011, WITH a branch segment the bank-level row has no
        // exact match for) against the seeded bank-level row, via the
        // Resolver's findByBankCode(cc, bank, null) fallback.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::GR_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('NATIONAL BANK OF GREECE S.A.', $result->bankName);
    }

    public function testBankOfSloveniaImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new BankOfSloveniaImporter(), new BankModel(), false, self::BSI_FIXTURE);

        self::assertSame('SI', $report->countryCode);
        self::assertSame('bsi', $report->sourceId);
        self::assertSame(4, $report->fetched);
        self::assertSame(4, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(4, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'SI',
            'bank_code'      => '01000',
            'branch_code'    => null,
            'name'           => 'BANKA SLOVENIJE',
            'city'           => 'LJUBLJANA',
            'bic'            => 'BSLJSI2XXXX',
            'source_id'      => 'bsi',
            'source_license' => 'Bank of Slovenia (cite source, no changes)',
        ]);

        // The header row must not have produced a row.
        $this->dontSeeInDatabase('banks', ['name' => 'NAME']);

        // The real proof: resolving the SI registry's own example IBAN
        // (bank code 26330, a fixture-only row -- see BankOfSloveniaImporterTest)
        // against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::SI_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('PRIMER BANKA D.D. (IBAN example fixture row)', $result->bankName);
    }

    public function testNationalBankOfSlovakiaImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new NationalBankOfSlovakiaImporter(), new BankModel(), false, self::NBS_FIXTURE);

        self::assertSame('SK', $report->countryCode);
        self::assertSame('nbs', $report->sourceId);
        self::assertSame(4, $report->fetched);
        self::assertSame(4, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(4, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'SK',
            'bank_code'      => '0200', // '200' zero-padded to 4 digits
            'branch_code'    => null,
            'name'           => 'Všeobecná úverová banka, a.s.', // UTF-8 accented char round trip
            'bic'            => 'SUBASKBX',
            'source_id'      => 'nbs',
            'source_license' => 'National Bank of Slovakia',
        ]);

        // The title/header preamble rows must not have produced rows.
        $this->dontSeeInDatabase('banks', ['name' => 'Poskytovateľ platobných služieb']);

        // The real proof: resolving the SK registry's own example IBAN
        // (bank code 1200, a fixture-only row -- see NationalBankOfSlovakiaImporterTest)
        // against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::SK_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Príklad Banka, a.s. (fixture — SK registry example bank code)', $result->bankName);
    }

    public function testBulgarianNationalBankImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new BulgarianNationalBankImporter(), new BankModel(), false, self::BNB_FIXTURE);

        self::assertSame('BG', $report->countryCode);
        self::assertSame('bnb', $report->sourceId);
        self::assertSame(5, $report->fetched);
        self::assertSame(5, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(5, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'BG',
            'bank_code'      => 'BNBG',
            'branch_code'    => null,
            'name'           => 'Българска народна банка', // UTF-8 Cyrillic round trip
            'bic'            => 'BNBGBGSF',
            'source_id'      => 'bnb',
            'source_license' => 'Bulgarian National Bank',
        ]);

        // The deduped sibling ("СЕБРА плащания", same 'BNBG' prefix) must not
        // have overwritten the primary row's name.
        $this->dontSeeInDatabase('banks', [
            'bank_code' => 'BNBG',
            'name'      => 'Българска народна банка СЕБРА плащания',
        ]);

        // The real proof: resolving the BNB's own example IBAN (bank code
        // 'BNBG') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::BG_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Българска народна банка', $result->bankName);
    }

    public function testNationalBankOfMoldovaImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new NationalBankOfMoldovaImporter(), new BankModel(), false, self::BNM_FIXTURE);

        self::assertSame('MD', $report->countryCode);
        self::assertSame('bnm', $report->sourceId);
        self::assertSame(5, $report->fetched);
        self::assertSame(5, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(5, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'MD',
            'bank_code'      => 'AG',
            'branch_code'    => null,
            'name'           => "BC'MAIB'S.A.",
            'bic'            => 'AGRNMD2X',
            'source_id'      => 'bnm',
            'source_license' => 'National Bank of Moldova',
        ]);

        // The sub-account/branch row (empty IBANIdentifier) must not have
        // produced its own row.
        $this->dontSeeInDatabase('banks', ['name' => "B.C.'VICTORIABANK'S.A. suc.nr.24 Ialoveni"]);

        // The real proof: resolving the BNM's own example IBAN (bank code
        // 'AG') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::MD_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame("BC'MAIB'S.A.", $result->bankName);
    }

    public function testNationalBankOfPolandImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new NationalBankOfPolandImporter(), new BankModel(), false, self::NBP_FIXTURE);

        self::assertSame('PL', $report->countryCode);
        self::assertSame('nbp', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'PL',
            'bank_code'      => '109',
            'branch_code'    => null,
            'name'           => 'Erste Bank Polska Spółka Akcyjna', // UTF-8 accented char round trip
            'source_id'      => 'nbp',
            'source_license' => 'Narodowy Bank Polski (public sector information, free reuse)',
        ]);

        // The real proof: resolving the EWIB registry's own example IBAN
        // (clearing code 10901014, rolled up to bank code '109') against the
        // seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::PL_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Erste Bank Polska Spółka Akcyjna', $result->bankName);
    }

    public function testCentralBankOfAzerbaijanImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new CentralBankOfAzerbaijanImporter(), new BankModel(), false, self::CBAR_FIXTURE);

        self::assertSame('AZ', $report->countryCode);
        self::assertSame('cbar', $report->sourceId);
        self::assertSame(5, $report->fetched);
        self::assertSame(5, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(5, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'AZ',
            'bank_code'      => 'NABZ',
            'branch_code'    => null,
            'name'           => 'AR Mərkəzi Bankı', // UTF-8 accented char round trip
            'bic'            => 'NABZAZ2C',
            'source_id'      => 'cbar',
            'source_license' => 'Central Bank of Azerbaijan',
        ]);

        // The <Branch> entry under <BranchOffices> (also SWIFTBIC NABZAZ2C)
        // must not have produced its own row.
        $this->dontSeeInDatabase('banks', ['name' => 'MB Biləsuvar Ərazi Idarəsi']);

        // The deduped duplicate-prefix sibling ('CTREAZ22') must not have
        // overwritten the first ('CTREAZ24') row.
        $this->dontSeeInDatabase('banks', ['bank_code' => 'CTRE', 'bic' => 'CTREAZ22']);

        // The real proof: resolving CBAR's own example IBAN (bank code
        // 'NABZ') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::AZ_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('AR Mərkəzi Bankı', $result->bankName);
    }

    public function testNationalBankOfBelgiumImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new NationalBankOfBelgiumImporter(), new BankModel(), false, $this->nbbFixture);

        self::assertSame('BE', $report->countryCode);
        self::assertSame('nbb', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'BE',
            'bank_code'      => '539',
            'branch_code'    => null,
            'name'           => 'Voorbeeldbank NV (fixture — BE registry example bank code)',
            'bic'            => 'GEBABEBB', // spaces stripped
            'source_id'      => 'nbb',
            'source_license' => 'National Bank of Belgium',
        ]);

        // The real proof: resolving the BE registry's own example IBAN
        // (bank code '539') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::BE_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Voorbeeldbank NV (fixture — BE registry example bank code)', $result->bankName);
    }

    public function testCroatianNationalBankImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new CroatianNationalBankImporter(), new BankModel(), false, $this->hnbFixture);

        self::assertSame('HR', $report->countryCode);
        self::assertSame('hnb', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'HR',
            'bank_code'      => '1001005',
            'branch_code'    => null,
            'name'           => 'HRVATSKA NARODNA BANKA',
            'bic'            => 'NBHRHR2X', // trailing/internal spaces stripped
            'source_id'      => 'hnb',
            'source_license' => 'Croatian National Bank (cite source, no changes)',
        ]);

        // A row with no published BIC must yield a null `bic`, not an empty string.
        $this->seeInDatabase('banks', [
            'country_code' => 'HR',
            'bank_code'    => '4501006',
            'name'         => 'AIRCASH d.o.o. Zagreb',
            'bic'          => null,
        ]);

        // The real proof: resolving the HNB registry's own example IBAN
        // (bank code '1001005') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::HR_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('HRVATSKA NARODNA BANKA', $result->bankName);
    }

    public function testLuxembourgBankersAssociationImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new LuxembourgBankersAssociationImporter(), new BankModel(), false, $this->abblFixture);

        self::assertSame('LU', $report->countryCode);
        self::assertSame('abbl', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'LU',
            'bank_code'      => '001',
            'branch_code'    => null,
            'name'           => "Banque et Caisse d'Epargne de l'Etat, Luxembourg (Spuerkeess)",
            'bic'            => 'BCEELULL', // spaces stripped
            'source_id'      => 'abbl',
            'source_license' => 'ABBL Luxembourg IBAN/BIC Register',
        ]);

        // The real proof: resolving the ABBL registry's own example IBAN
        // (bank code '001') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::LU_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame("Banque et Caisse d'Epargne de l'Etat, Luxembourg (Spuerkeess)", $result->bankName);
    }

    public function testCentralBankOfMaltaImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new CentralBankOfMaltaImporter(), new BankModel(), false, $this->cbmFixture);

        self::assertSame('MT', $report->countryCode);
        self::assertSame('cbm', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'MT',
            'bank_code'      => 'MALT',
            'branch_code'    => null,
            'name'           => 'Central Bank of Malta',
            'bic'            => 'MALTMTMT',
            'source_id'      => 'cbm',
            'source_license' => 'Central Bank of Malta',
        ]);

        // The deduped sibling ('PYMXMTMTMAL') must not have overwritten the
        // first ('PYMXMTMTXXX') row.
        $this->dontSeeInDatabase('banks', ['bank_code' => 'PYMX', 'bic' => 'PYMXMTMTMAL']);

        // The real proof: resolving the CBM registry's own example IBAN
        // (bank code 'MALT') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::MT_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Central Bank of Malta', $result->bankName);
    }

    public function testMagyarNemzetiBankImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new MagyarNemzetiBankImporter(), new BankModel(), false, $this->mnbFixture);

        self::assertSame('HU', $report->countryCode);
        self::assertSame('mnb', $report->sourceId);
        self::assertSame(2, $report->fetched);
        self::assertSame(2, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(2, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'HU',
            'bank_code'      => '117',
            'branch_code'    => null,
            'name'           => 'OTP Bank Nyrt. (fixture — HU registry example bank code)',
            'bic'            => 'OTPVHUHB',
            'source_id'      => 'mnb',
            'source_license' => 'Magyar Nemzeti Bank',
        ]);

        // The deduped sibling branch office row (also bank '117') must not
        // have overwritten the first occurrence's name.
        $this->dontSeeInDatabase('banks', [
            'bank_code' => '117',
            'name'      => 'OTP Budapesti r., II. Széna tér',
        ]);

        // The real proof: resolving the MNB registry's own example IBAN
        // (bank code '117') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::HU_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('OTP Bank Nyrt. (fixture — HU registry example bank code)', $result->bankName);
    }

    public function testBitsNorwayImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new BitsNorwayImporter(), new BankModel(), false, $this->bitsFixture);

        self::assertSame('NO', $report->countryCode);
        self::assertSame('bits', $report->sourceId);
        self::assertSame(2, $report->fetched);
        self::assertSame(2, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(2, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'NO',
            'bank_code'      => '8601',
            'branch_code'    => null,
            'name'           => 'Danske Bank (fixture — NO registry example bank code)',
            'bic'            => 'DABANO22',
            'source_id'      => 'bits',
            'source_license' => 'Bits AS (Norway)',
        ]);

        // The real proof: resolving the Bits registry's own example IBAN
        // (bank code '8601') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::NO_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Danske Bank (fixture — NO registry example bank code)', $result->bankName);
    }

    public function testNationalBankOfGeorgiaImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new NationalBankOfGeorgiaImporter(), new BankModel(), false, $this->nbgFixture);

        self::assertSame('GE', $report->countryCode);
        self::assertSame('nbg', $report->sourceId);
        self::assertSame(2, $report->fetched);
        self::assertSame(2, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(2, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'GE',
            'bank_code'      => 'NB',
            'branch_code'    => null,
            'name'           => 'საქართველოს ეროვნული ბანკი (fixture — GE registry example bank code)',
            'bic'            => 'BNLNGE22', // leading space trimmed
            'source_id'      => 'nbg',
            'source_license' => 'National Bank of Georgia',
        ]);

        // The real proof: resolving the NBG registry's own example IBAN
        // (bank code 'NB') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::GE_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('საქართველოს ეროვნული ბანკი (fixture — GE registry example bank code)', $result->bankName);
    }

    public function testBankOfIsraelImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new BankOfIsraelImporter(), new BankModel(), false, self::BOI_FIXTURE);

        self::assertSame('IL', $report->countryCode);
        self::assertSame('boi', $report->sourceId);
        self::assertSame(4, $report->fetched);
        self::assertSame(4, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(4, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'IL',
            'bank_code'      => '010', // source Bank_Code '10' zero-padded to 3 digits
            'branch_code'    => null,
            'name'           => 'Bank Leumi Le-Israel B.M',
            'source_id'      => 'boi',
            'source_license' => 'Bank of Israel (data.gov.il, other-open)',
        ]);

        // The second branch row for the same Bank_Code ('10') must not have
        // produced a second row.
        self::assertSame(1, $this->db->table('banks')->where('bank_code', '010')->countAllResults());

        // The real proof: resolving the Bank of Israel's own example IBAN
        // (bank code '010') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::IL_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Bank Leumi Le-Israel B.M', $result->bankName);
    }

    public function testNationalBankOfUkraineImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new NationalBankOfUkraineImporter(), new BankModel(), false, self::NBU_FIXTURE);

        self::assertSame('UA', $report->countryCode);
        self::assertSame('nbu', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'UA',
            'bank_code'      => '305299',
            'branch_code'    => null,
            'name'           => 'АКЦІОНЕРНЕ ТОВАРИСТВО КОМЕРЦІЙНИЙ БАНК "ПРИВАТБАНК"',
            'source_id'      => 'nbu',
            'source_license' => 'National Bank of Ukraine (open data)',
        ]);

        // The real proof: resolving the NBU registry's own example IBAN
        // (MFO bank code '305299') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::UA_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('АКЦІОНЕРНЕ ТОВАРИСТВО КОМЕРЦІЙНИЙ БАНК "ПРИВАТБАНК"', $result->bankName);
    }

    public function testNationalBankOfKazakhstanImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new NationalBankOfKazakhstanImporter(), new BankModel(), false, self::NBK_FIXTURE);

        self::assertSame('KZ', $report->countryCode);
        self::assertSame('nbk', $report->sourceId);
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'KZ',
            'bank_code'      => '125',
            'branch_code'    => null,
            'name'           => 'ForteBank JSC (fixture — KZ registry example bank code)',
            'bic'            => 'IRTYKZKA',
            'source_id'      => 'nbk',
            'source_license' => 'National Bank of Kazakhstan (open data)',
        ]);

        // The real proof: resolving the NBK registry's own example IBAN
        // (bank code '125') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::KZ_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('ForteBank JSC (fixture — KZ registry example bank code)', $result->bankName);
    }

    public function testSwedenBankInfrastructureImporterImportsRowsWithProvenanceAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new SwedenBankInfrastructureImporter(), new BankModel(), false, self::SE_FIXTURE);

        self::assertSame('SE', $report->countryCode);
        self::assertSame('bankinfrastruktur', $report->sourceId);
        // 4 raw data lines, but the duplicate Nordea IbanId (300) dedups to
        // one row, so the importer yields 3.
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'SE',
            'bank_code'      => '500', // IbanId, NOT clearing '5000'
            'branch_code'    => null,
            'name'           => 'Skandinaviska Enskilda Banken',
            'bic'            => 'ESSESESS',
            'source_id'      => 'bankinfrastruktur',
            'source_license' => 'MIT (Bankinfrastruktur BankData)',
        ]);

        // The duplicate Nordea range must not have produced a second row.
        self::assertSame(1, $this->db->table('banks')->where('bank_code', '300')->countAllResults());

        // The clearing number must never have leaked in as a bank code.
        $this->dontSeeInDatabase('banks', ['bank_code' => '5000']);

        // The real proof: resolving SIX/SWIFT's own SE example IBAN (bank
        // code '500') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::SE_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Skandinaviska Enskilda Banken', $result->bankName);
    }

    public function testRegafiImporterImportsFranceAndMonacoRowsAndResolvesExampleIbans(): void
    {
        $runner = new ImportRunner();
        $model  = new BankModel();

        $frReport = $runner->run(new RegafiImporter('FR'), $model, false, self::REGAFI_FIXTURE);
        $mcReport = $runner->run(new RegafiImporter('MC'), $model, false, self::REGAFI_FIXTURE);

        // FR: 30003 + (20041, 20042) + 07788 = 4 rows (empty-cib entity and
        // the two Monaco records excluded). MC: 12739 + 10160 = 2 rows.
        self::assertSame('FR', $frReport->countryCode);
        self::assertSame(4, $frReport->imported);
        self::assertSame('MC', $mcReport->countryCode);
        self::assertSame(2, $mcReport->imported);

        self::assertSame(6, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'FR',
            'bank_code'      => '30003',
            'branch_code'    => null,
            'name'           => 'Société Générale',
            'bic'            => null, // REGAFI carries no BIC
            'source_id'      => 'regafi',
            'source_license' => 'Licence Ouverte / Etalab (attribution)',
        ]);

        // The two-CIB entity produced two rows.
        $this->seeInDatabase('banks', ['country_code' => 'FR', 'bank_code' => '20041', 'name' => 'La Banque Postale']);
        $this->seeInDatabase('banks', ['country_code' => 'FR', 'bank_code' => '20042', 'name' => 'La Banque Postale']);
        // The short code was zero-padded to 5 digits.
        $this->seeInDatabase('banks', ['country_code' => 'FR', 'bank_code' => '07788']);

        // Monaco entities are keyed under MC, never FR.
        $this->seeInDatabase('banks', [
            'country_code' => 'MC',
            'bank_code'    => '12739',
            'name'         => 'CFM Indosuez Wealth',
            'source_id'    => 'regafi',
        ]);
        $this->dontSeeInDatabase('banks', ['country_code' => 'FR', 'bank_code' => '12739']);

        // The real proof: resolving MOD-97-valid FR and MC example IBANs.
        $iban = new Iban(provider: new DatabaseProvider(new BankModel()));

        $fr = $iban->resolve(self::FR_EXAMPLE_IBAN);
        self::assertTrue($fr->isResolved());
        self::assertSame('Société Générale', $fr->bankName);

        $mc = $iban->resolve(self::MC_EXAMPLE_IBAN);
        self::assertTrue($mc->isResolved());
        self::assertSame('CFM Indosuez Wealth', $mc->bankName);
    }

    public function testEstonianBankingAssociationImporterImportsRowsAndResolvesTheExampleIban(): void
    {
        $report = (new ImportRunner())->run(new EstonianBankingAssociationImporter(), new BankModel(), false, self::EE_FIXTURE);

        self::assertSame('EE', $report->countryCode);
        self::assertSame('pangaliit', $report->sourceId);
        // Swedbank, SEB, Luminor x2 (96 + 17), TBB = 5 rows.
        self::assertSame(5, $report->imported);

        self::assertSame(5, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'EE',
            'bank_code'      => '22',
            'branch_code'    => null,
            'name'           => 'Swedbank AS',
            'bic'            => 'HABAEE2X',
            'source_id'      => 'pangaliit',
        ]);

        // Both Luminor codes were seeded; the leading-zero TBB code survived.
        $this->seeInDatabase('banks', ['country_code' => 'EE', 'bank_code' => '96', 'name' => 'Luminor Bank AS']);
        $this->seeInDatabase('banks', ['country_code' => 'EE', 'bank_code' => '17', 'name' => 'Luminor Bank AS']);
        $this->seeInDatabase('banks', ['country_code' => 'EE', 'bank_code' => '00', 'name' => 'AS TBB pank']);

        // The real proof: resolving the SWIFT EE example IBAN (bank code '22').
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::EE_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Swedbank AS', $result->bankName);
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

    public function testAllTwentyFiveImportersCanCoexistInTheSameBanksTable(): void
    {
        $runner = new ImportRunner();
        $model  = new BankModel();

        $runner->run(new OenbImporter(), $model, false, self::OENB_FIXTURE);
        $runner->run(new BundesbankImporter(), $model, false, self::BUNDESBANK_FIXTURE);
        $runner->run(new SixImporter(), $model, false, self::SIX_FIXTURE);
        $runner->run(new BetaalverenigingImporter(), $model, false, self::BETAALVERENIGING_FIXTURE);
        $runner->run(new BancoDeEspanaImporter(), $model, false, self::BDE_FIXTURE);
        $runner->run(new CzechNationalBankImporter(), $model, false, self::CNB_FIXTURE);
        $runner->run(new HellenicBankAssociationImporter(), $model, false, self::HBA_FIXTURE);
        $runner->run(new BankOfSloveniaImporter(), $model, false, self::BSI_FIXTURE);
        $runner->run(new NationalBankOfSlovakiaImporter(), $model, false, self::NBS_FIXTURE);
        $runner->run(new BulgarianNationalBankImporter(), $model, false, self::BNB_FIXTURE);
        $runner->run(new NationalBankOfMoldovaImporter(), $model, false, self::BNM_FIXTURE);
        $runner->run(new NationalBankOfPolandImporter(), $model, false, self::NBP_FIXTURE);
        $runner->run(new CentralBankOfAzerbaijanImporter(), $model, false, self::CBAR_FIXTURE);
        $runner->run(new NationalBankOfBelgiumImporter(), $model, false, $this->nbbFixture);
        $runner->run(new CroatianNationalBankImporter(), $model, false, $this->hnbFixture);
        $runner->run(new LuxembourgBankersAssociationImporter(), $model, false, $this->abblFixture);
        $runner->run(new CentralBankOfMaltaImporter(), $model, false, $this->cbmFixture);
        $runner->run(new MagyarNemzetiBankImporter(), $model, false, $this->mnbFixture);
        $runner->run(new BitsNorwayImporter(), $model, false, $this->bitsFixture);
        $runner->run(new NationalBankOfGeorgiaImporter(), $model, false, $this->nbgFixture);
        $runner->run(new BankOfIsraelImporter(), $model, false, self::BOI_FIXTURE);
        $runner->run(new NationalBankOfUkraineImporter(), $model, false, self::NBU_FIXTURE);
        $runner->run(new NationalBankOfKazakhstanImporter(), $model, false, self::NBK_FIXTURE);
        $runner->run(new LiechtensteinImporter(), $model, false, self::SIX_FIXTURE);
        $runner->run(new BrazilianCentralBankImporter(), $model, false, self::BCB_FIXTURE);

        // 2 (AT) + 3 (DE) + 2 (CH) + 3 (NL) + 3 (ES) + 8 (CZ) + 5 (GR) + 4 (SI)
        // + 4 (SK) + 5 (BG) + 5 (MD) + 3 (PL) + 5 (AZ) + 3 (BE) + 3 (HR)
        // + 3 (LU) + 3 (MT) + 2 (HU) + 2 (NO) + 2 (GE) + 4 (IL) + 3 (UA)
        // + 3 (KZ) + 1 (LI) + 3 (BR) = 84.
        self::assertSame(84, $this->db->table('banks')->countAllResults());

        $liModel = (new BankModel())->findByNaturalKey('LI', '08810', null);
        self::assertIsArray($liModel);
        self::assertSame('Liechtensteinische Landesbank AG', $liModel['name']);

        $brModel = (new BankModel())->findByNaturalKey('BR', '00360305', null);
        self::assertIsArray($brModel);
        self::assertSame('CAIXA ECONOMICA FEDERAL', $brModel['name']);

        $chModel = (new BankModel())->findByNaturalKey('CH', '09000', null);
        self::assertIsArray($chModel);
        self::assertSame('PostFinance AG', $chModel['name']);

        $nlModel = (new BankModel())->findByNaturalKey('NL', 'RABO', null);
        self::assertIsArray($nlModel);
        self::assertSame('RABOBANK', $nlModel['name']);

        $esModel = (new BankModel())->findByNaturalKey('ES', '2100', null);
        self::assertIsArray($esModel);
        self::assertSame('Caixabank, S.A.', $esModel['name']);

        $czModel = (new BankModel())->findByNaturalKey('CZ', '0800', null);
        self::assertIsArray($czModel);
        self::assertSame('Česká spořitelna, a.s.', $czModel['name']);

        $grModel = (new BankModel())->findByNaturalKey('GR', '011', null);
        self::assertIsArray($grModel);
        self::assertSame('NATIONAL BANK OF GREECE S.A.', $grModel['name']);

        $siModel = (new BankModel())->findByNaturalKey('SI', '26330', null);
        self::assertIsArray($siModel);
        self::assertSame('PRIMER BANKA D.D. (IBAN example fixture row)', $siModel['name']);

        $skModel = (new BankModel())->findByNaturalKey('SK', '1200', null);
        self::assertIsArray($skModel);
        self::assertSame('Príklad Banka, a.s. (fixture — SK registry example bank code)', $skModel['name']);

        $bgModel = (new BankModel())->findByNaturalKey('BG', 'BNBG', null);
        self::assertIsArray($bgModel);
        self::assertSame('Българска народна банка', $bgModel['name']);

        $mdModel = (new BankModel())->findByNaturalKey('MD', 'AG', null);
        self::assertIsArray($mdModel);
        self::assertSame("BC'MAIB'S.A.", $mdModel['name']);

        $plModel = (new BankModel())->findByNaturalKey('PL', '109', null);
        self::assertIsArray($plModel);
        self::assertSame('Erste Bank Polska Spółka Akcyjna', $plModel['name']);

        $azModel = (new BankModel())->findByNaturalKey('AZ', 'NABZ', null);
        self::assertIsArray($azModel);
        self::assertSame('AR Mərkəzi Bankı', $azModel['name']);

        $beModel = (new BankModel())->findByNaturalKey('BE', '539', null);
        self::assertIsArray($beModel);
        self::assertSame('Voorbeeldbank NV (fixture — BE registry example bank code)', $beModel['name']);

        $hrModel = (new BankModel())->findByNaturalKey('HR', '1001005', null);
        self::assertIsArray($hrModel);
        self::assertSame('HRVATSKA NARODNA BANKA', $hrModel['name']);

        $luModel = (new BankModel())->findByNaturalKey('LU', '001', null);
        self::assertIsArray($luModel);
        self::assertSame("Banque et Caisse d'Epargne de l'Etat, Luxembourg (Spuerkeess)", $luModel['name']);

        $mtModel = (new BankModel())->findByNaturalKey('MT', 'MALT', null);
        self::assertIsArray($mtModel);
        self::assertSame('Central Bank of Malta', $mtModel['name']);

        $huModel = (new BankModel())->findByNaturalKey('HU', '117', null);
        self::assertIsArray($huModel);
        self::assertSame('OTP Bank Nyrt. (fixture — HU registry example bank code)', $huModel['name']);

        $noModel = (new BankModel())->findByNaturalKey('NO', '8601', null);
        self::assertIsArray($noModel);
        self::assertSame('Danske Bank (fixture — NO registry example bank code)', $noModel['name']);

        $geModel = (new BankModel())->findByNaturalKey('GE', 'NB', null);
        self::assertIsArray($geModel);
        self::assertSame('საქართველოს ეროვნული ბანკი (fixture — GE registry example bank code)', $geModel['name']);

        $ilModel = (new BankModel())->findByNaturalKey('IL', '010', null);
        self::assertIsArray($ilModel);
        self::assertSame('Bank Leumi Le-Israel B.M', $ilModel['name']);

        $uaModel = (new BankModel())->findByNaturalKey('UA', '305299', null);
        self::assertIsArray($uaModel);
        self::assertSame('АКЦІОНЕРНЕ ТОВАРИСТВО КОМЕРЦІЙНИЙ БАНК "ПРИВАТБАНК"', $uaModel['name']);

        $kzModel = (new BankModel())->findByNaturalKey('KZ', '125', null);
        self::assertIsArray($kzModel);
        self::assertSame('ForteBank JSC (fixture — KZ registry example bank code)', $kzModel['name']);
    }

    public function testEpcRegisterImporterImportsGbRowsWithProvenanceAndResolvesStarling(): void
    {
        $report = (new ImportRunner())->run(new EpcRegisterImporter('GB'), new BankModel(), false, self::EPC_FIXTURE);

        self::assertSame('GB', $report->countryCode);
        self::assertSame('epc', $report->sourceId);
        // The importer itself already dedups (LOYD) and skips (a past
        // Scheme Leaving Date row, an empty-BIC row) before ImportRunner
        // ever sees a row -- so `fetched` reflects the 3 GB rows the
        // importer actually yields, not the 6 raw GB lines in the fixture.
        self::assertSame(3, $report->fetched);
        self::assertSame(3, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(3, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'GB',
            'bank_code'      => 'SRLG',
            'branch_code'    => null,
            'name'           => 'Starling Bank Limited',
            'bic'            => 'SRLGGB3LXXX',
            'source_id'      => 'epc',
            'source_license' => 'EPC SEPA Register (credit EPC, no resale as-is)',
            'sepa_sct'       => 1,
        ]);

        $this->seeInDatabase('banks', [
            'country_code' => 'GB',
            'bank_code'    => 'LOYD',
            'name'         => 'Lloyds Bank plc', // first occurrence wins over the deduped LOYDJES1XXX sibling
        ]);

        // The real proof: resolving a MOD-97-valid GB IBAN for Starling's
        // bank_code ('SRLG') against the seeded bank-level row, including
        // the SEPA SCT reachability flag this importer is the whole point of.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::GB_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Starling Bank Limited', $result->bankName);
        self::assertTrue($result->sepaSct);
    }

    public function testEpcRegisterImporterImportsIeRowsWithProvenanceAndResolvesAlliedIrishBanks(): void
    {
        $report = (new ImportRunner())->run(new EpcRegisterImporter('IE'), new BankModel(), false, self::EPC_FIXTURE);

        self::assertSame('IE', $report->countryCode);
        self::assertSame('epc', $report->sourceId);
        self::assertSame(2, $report->fetched);
        self::assertSame(2, $report->imported);
        self::assertSame(0, $report->skipped);

        self::assertSame(2, $this->db->table('banks')->countAllResults());

        $this->seeInDatabase('banks', [
            'country_code'   => 'IE',
            'bank_code'      => 'AIBK',
            'branch_code'    => null,
            'name'           => 'Allied Irish Banks plc',
            'bic'            => 'AIBKIE2DXXX',
            'source_id'      => 'epc',
            'source_license' => 'EPC SEPA Register (credit EPC, no resale as-is)',
            'sepa_sct'       => 1,
        ]);

        // The real proof: resolving a MOD-97-valid IE IBAN for AIB's
        // bank_code ('AIBK') against the seeded bank-level row.
        $iban   = new Iban(provider: new DatabaseProvider(new BankModel()));
        $result = $iban->resolve(self::IE_EXAMPLE_IBAN);

        self::assertTrue($result->isResolved());
        self::assertSame('Allied Irish Banks plc', $result->bankName);
        self::assertTrue($result->sepaSct);
    }

    /**
     * Proves the remaining three of the five EPC-registered countries
     * (RO/LV/GI) end-to-end too, alongside GB/IE above -- all five are the
     * whole point of this importer's registration batch.
     */
    public function testEpcRegisterImporterResolvesRomaniaLatviaAndGibraltarExampleIbans(): void
    {
        $runner = new ImportRunner();
        $model  = new BankModel();

        $runner->run(new EpcRegisterImporter('RO'), $model, false, self::EPC_FIXTURE);
        $runner->run(new EpcRegisterImporter('LV'), $model, false, self::EPC_FIXTURE);
        $runner->run(new EpcRegisterImporter('GI'), $model, false, self::EPC_FIXTURE);

        $this->seeInDatabase('banks', [
            'country_code' => 'RO',
            'bank_code'    => 'BTRL',
            'source_id'    => 'epc',
            'sepa_sct'     => 1,
        ]);
        $this->seeInDatabase('banks', [
            'country_code' => 'LV',
            'bank_code'    => 'HABA',
            'source_id'    => 'epc',
            'sepa_sct'     => 1,
        ]);
        $this->seeInDatabase('banks', [
            'country_code' => 'GI',
            'bank_code'    => 'XAPO',
            'source_id'    => 'epc',
            'sepa_sct'     => 1,
        ]);

        $iban = new Iban(provider: new DatabaseProvider(new BankModel()));

        $ro = $iban->resolve(self::RO_EXAMPLE_IBAN);
        self::assertTrue($ro->isResolved());
        self::assertSame('Banca Transilvania S.A.', $ro->bankName);
        self::assertTrue($ro->sepaSct);

        $lv = $iban->resolve(self::LV_EXAMPLE_IBAN);
        self::assertTrue($lv->isResolved());
        self::assertSame('AS Swedbank', $lv->bankName);
        self::assertTrue($lv->sepaSct);

        $gi = $iban->resolve(self::GI_EXAMPLE_IBAN);
        self::assertTrue($gi->isResolved());
        self::assertSame('XAPO Bank Limited', $gi->bankName);
        self::assertTrue($gi->sepaSct);
    }

    public function testEpcRegisterImporterExcludesTheFrenchRowFromEveryTargetCountryImport(): void
    {
        $runner = new ImportRunner();
        $model  = new BankModel();

        $runner->run(new EpcRegisterImporter('GB'), $model, false, self::EPC_FIXTURE);
        $runner->run(new EpcRegisterImporter('IE'), $model, false, self::EPC_FIXTURE);
        $runner->run(new EpcRegisterImporter('RO'), $model, false, self::EPC_FIXTURE);
        $runner->run(new EpcRegisterImporter('LV'), $model, false, self::EPC_FIXTURE);
        $runner->run(new EpcRegisterImporter('GI'), $model, false, self::EPC_FIXTURE);

        $this->dontSeeInDatabase('banks', ['bank_code' => 'BNPA']);
        $this->dontSeeInDatabase('banks', ['name' => 'BNP Paribas SA']);
    }
}
