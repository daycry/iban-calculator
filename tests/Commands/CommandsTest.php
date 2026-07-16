<?php

declare(strict_types=1);

namespace Tests\Commands;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\StreamFilterTrait;
use Daycry\Iban\Commands\BicCommand;
use Daycry\Iban\Commands\ParseCommand;
use Daycry\Iban\Commands\PublishCommand;
use Daycry\Iban\Commands\ResolveCommand;
use Daycry\Iban\Commands\UpdateCommand;
use Daycry\Iban\Commands\ValidateCommand;
use Daycry\Iban\Config\Iban as IbanConfig;
use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Core\Mod97;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Import\ImporterRegistry;

/**
 * Exercises the 4 `daycry/iban` spark commands (T-41/42/43) through CI4's
 * real command discovery + execution path: `service('commands')->run()`,
 * the same call `CodeIgniter\CLI\Console::run()` makes for a real
 * `php spark ...` invocation.
 *
 * Output capture: `CLI::write()`/`CLI::error()`/`CLI::table()` all write via
 * `CLI::fwrite(STDOUT|STDERR, ...)`, which bypasses PHP's `ob_*` output
 * buffering -- so the `command()` helper's `ob_start()`/`ob_get_contents()`
 * trick does NOT see this output. This test instead attaches CI4's
 * `CITestStreamFilter` directly to STDOUT/STDERR via `StreamFilterTrait`
 * (the same mechanism CI4's own `MockInputOutput` test double uses).
 *
 * Flag capture (`--national`, `--json`, ...): `CLI::getOption()` reads a
 * process-wide static array populated once from the real `$_SERVER['argv']`
 * by `CLI::init()` (see that method's "clear segments & options to keep
 * testing clean" inline comment) -- NOT from the `$params` array passed to
 * `run()`. `runSpark()` below reproduces exactly what `Console::run()` does
 * for a real invocation: seed `$_SERVER['argv']`, call `CLI::init()` to
 * reparse it, then derive `$params` the same way
 * (`array_merge(CLI::getSegments(), CLI::getOptions())` with the command
 * name shifted off) before calling `service('commands')->run()`.
 *
 * Exit codes: `Commands::run()` returns the command's own `run()` return
 * value directly (`int`), so `runSpark()` captures it without any further
 * instrumentation -- no need to instantiate commands manually.
 *
 * @see .superpowers/sdd/task-41-43-brief.md
 */
final class CommandsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use StreamFilterTrait;

    // `iban:update`'s file-based import tests (V-7a) write to the real
    // `banks` table via `BankModel`/`ImportRunner`, so this class also
    // migrates/refreshes the `tests` SQLite `:memory:` database, same setup
    // as `tests/Import/ImportRunnerTest.php`.
    protected $namespace   = 'Daycry\Iban';
    protected $DBGroup     = 'tests';
    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;

    private const VALID_ES_IBAN = 'ES9121000418450200051332';

    private const OENB_FIXTURE             = __DIR__ . '/../Fixtures/import/oenb_sample.csv';
    private const BUNDESBANK_FIXTURE       = __DIR__ . '/../Fixtures/import/bundesbank_sample.txt';
    private const SIX_FIXTURE              = __DIR__ . '/../Fixtures/import/six_sample.csv';
    private const BETAALVERENIGING_FIXTURE = __DIR__ . '/../Fixtures/import/betaalvereniging_sample.csv';
    private const BDE_FIXTURE              = __DIR__ . '/../Fixtures/import/bde_sample.csv';

    // Same length/structure as VALID_ES_IBAN but with the check digits
    // ('91' -> '90') broken, so MOD-97 fails: deterministic ChecksumFailed.
    private const CHECKSUM_FAILED_ES_IBAN = 'ES9021000418450200051332';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStreamFilterTrait();
    }

    protected function tearDown(): void
    {
        // A couple of `iban:validate` tests below mutate the shared
        // `Config\Iban` singleton to prove `$checkNationalByDefault` is
        // actually consumed when `--national` isn't passed; undo that so
        // later tests keep seeing the documented default (mirrors
        // `Tests\Config\ServicesTest` / `Tests\Helpers\IbanHelperTest`).
        config(IbanConfig::class)->checkNationalByDefault = false;

        $this->tearDownStreamFilterTrait();

        parent::tearDown();
    }

    /**
     * Simulates `php spark <argv...>`: seeds `$_SERVER['argv']`, re-parses
     * it via `CLI::init()`, derives `$params` exactly like
     * `CodeIgniter\CLI\Console::run()` does, then runs the command through
     * the real `service('commands')` discovery/dispatch path.
     *
     * @param list<string> $argv e.g. ['iban:validate', 'ES91...', '--json']
     *
     * @return array{0: int, 1: string} [exitCode, captured STDOUT]
     */
    private function runSpark(array $argv): array
    {
        service('superglobals')->setServer('argv', array_merge(['spark'], $argv));
        CLI::init();

        $params  = array_merge(CLI::getSegments(), CLI::getOptions());
        $command = array_shift($params);

        if ($command === null) {
            self::fail('runSpark() was called with an empty $argv.');
        }

        $this->resetStreamFilterBuffer();

        $exitCode = service('commands')->run($command, $params);

        return [$exitCode, $this->getStreamFilterBuffer()];
    }

    /**
     * Builds a MOD-97-valid ES IBAN whose national (mod-11) check digits are
     * wrong -- same construction as `tests/Core/ValidatorNationalTest.php`
     * (T-27): correct bank/branch/account, but a deliberately wrong
     * national DC ('46' instead of '45') baked into the BBAN, with the
     * IBAN-level check digits recomputed over that (bad) BBAN via
     * `Mod97::checkDigits()` so the assembled IBAN still passes MOD-97.
     */
    private function esIbanWithBadNationalCheckDigits(): string
    {
        $bank    = '2100';
        $branch  = '0418';
        $badDc   = '46'; // correct value would be '45'
        $account = '0200051332';

        $bban = $bank . $branch . $badDc . $account;

        $checkDigits = (new Mod97())->checkDigits('ES', $bban);

        return 'ES' . $checkDigits . $bban;
    }

    // -- iban:validate ---------------------------------------------------

    public function testValidateValidIbanPrintsValidAndExitsSuccess(): void
    {
        [$exit, $output] = $this->runSpark(['iban:validate', self::VALID_ES_IBAN]);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('VALID', $output);
    }

    public function testValidateInvalidIbanPrintsViolationCodeAndExitsError(): void
    {
        [$exit, $output] = $this->runSpark(['iban:validate', self::CHECKSUM_FAILED_ES_IBAN]);

        self::assertSame(EXIT_ERROR, $exit);
        self::assertStringContainsString(ViolationCode::ChecksumFailed->value, $output);
    }

    public function testValidateJsonOutputIsParseableAndHasValidBool(): void
    {
        [$exit, $output] = $this->runSpark(['iban:validate', self::VALID_ES_IBAN, '--json']);

        self::assertSame(EXIT_SUCCESS, $exit);

        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('valid', $decoded);
        self::assertTrue($decoded['valid']);
        self::assertNull($decoded['violation']);
    }

    public function testValidateJsonOutputReportsViolationForAnInvalidIban(): void
    {
        [$exit, $output] = $this->runSpark(['iban:validate', self::CHECKSUM_FAILED_ES_IBAN, '--json']);

        self::assertSame(EXIT_ERROR, $exit);

        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertFalse($decoded['valid']);
        self::assertSame(ViolationCode::ChecksumFailed->value, $decoded['violation']['code']);
    }

    public function testValidateNationalFlagFailsAMod97ValidButNationallyInvalidEsIban(): void
    {
        $iban = $this->esIbanWithBadNationalCheckDigits();

        // Without --national: MOD-97-valid, so structurally VALID.
        [$exitWithout, $outputWithout] = $this->runSpark(['iban:validate', $iban]);
        self::assertSame(EXIT_SUCCESS, $exitWithout);
        self::assertStringContainsString('VALID', $outputWithout);

        // With --national: the ES national (mod-11) check now runs and fails.
        [$exitWith, $outputWith] = $this->runSpark(['iban:validate', $iban, '--national']);
        self::assertSame(EXIT_ERROR, $exitWith);
        self::assertStringContainsString(ViolationCode::NationalCheckFailed->value, $outputWith);
    }

    /**
     * V-3: when `--national` isn't passed at all, `iban:validate` now
     * consults `Config\Iban::$checkNationalByDefault` instead of always
     * defaulting to `false` -- with it set `true`, a MOD-97-valid but
     * nationally-invalid ES IBAN fails even without the flag.
     */
    public function testValidateWithoutNationalFlagHonorsConfiguredCheckNationalByDefaultTrue(): void
    {
        config(IbanConfig::class)->checkNationalByDefault = true;

        $iban = $this->esIbanWithBadNationalCheckDigits();

        [$exit, $output] = $this->runSpark(['iban:validate', $iban]);

        self::assertSame(EXIT_ERROR, $exit);
        self::assertStringContainsString(ViolationCode::NationalCheckFailed->value, $output);
    }

    /**
     * Passing `--national` explicitly still works when the config default
     * is already `true` (redundant, but confirms the flag isn't broken by
     * the config wiring).
     */
    public function testValidateExplicitNationalFlagStillWorksWhenConfigDefaultIsTrue(): void
    {
        config(IbanConfig::class)->checkNationalByDefault = true;

        $iban = $this->esIbanWithBadNationalCheckDigits();

        [$exit, $output] = $this->runSpark(['iban:validate', $iban, '--national']);

        self::assertSame(EXIT_ERROR, $exit);
        self::assertStringContainsString(ViolationCode::NationalCheckFailed->value, $output);
    }

    // -- iban:parse --------------------------------------------------------

    public function testParseValidIbanPrintsTableWithCountryCode(): void
    {
        [$exit, $output] = $this->runSpark(['iban:parse', self::VALID_ES_IBAN]);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('ES', $output);
    }

    public function testParseJsonOutputHasCountryCode(): void
    {
        [$exit, $output] = $this->runSpark(['iban:parse', self::VALID_ES_IBAN, '--json']);

        self::assertSame(EXIT_SUCCESS, $exit);

        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertSame('ES', $decoded['countryCode']);
    }

    public function testParseInvalidIbanErrorsAndExitsError(): void
    {
        [$exit, $output] = $this->runSpark(['iban:parse', self::CHECKSUM_FAILED_ES_IBAN]);

        self::assertSame(EXIT_ERROR, $exit);
        self::assertStringContainsString('Invalid IBAN', $output);
    }

    // -- iban:resolve ------------------------------------------------------

    public function testResolveWithEmptyDbIncludesNoProviderDataNote(): void
    {
        [$exit, $output] = $this->runSpark(['iban:resolve', self::VALID_ES_IBAN]);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('no provider data', $output);
    }

    public function testResolveJsonOutputReportsIsResolvedFalse(): void
    {
        [$exit, $output] = $this->runSpark(['iban:resolve', self::VALID_ES_IBAN, '--json']);

        self::assertSame(EXIT_SUCCESS, $exit);

        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertFalse($decoded['isResolved']);
        self::assertNull($decoded['bankName']);
        self::assertArrayHasKey('resolvedBy', $decoded);
        self::assertNull($decoded['resolvedBy']);
    }

    public function testResolveInvalidIbanErrorsAndExitsError(): void
    {
        [$exit, $output] = $this->runSpark(['iban:resolve', self::CHECKSUM_FAILED_ES_IBAN]);

        self::assertSame(EXIT_ERROR, $exit);
        self::assertStringContainsString('Invalid IBAN', $output);
    }

    // -- iban:update -------------------------------------------------------

    /**
     * V-7a bundled the first two concrete official-source importers --
     * `OenbImporter` (AT) and `BundesbankImporter` (DE) -- into
     * `ImporterRegistry::registerDefaults()` (empty since V-6). V-7b added
     * three more -- `SixImporter` (CH), `BetaalverenigingImporter` (NL) and
     * `BancoDeEspanaImporter` (ES). v1.2 added four more --
     * `CzechNationalBankImporter` (CZ), `HellenicBankAssociationImporter`
     * (GR), `BankOfSloveniaImporter` (SI) and
     * `NationalBankOfSlovakiaImporter` (SK). This v1.2 follow-up batch adds
     * four more XML-sourced importers -- `BulgarianNationalBankImporter`
     * (BG), `NationalBankOfMoldovaImporter` (MD),
     * `NationalBankOfPolandImporter` (PL) and
     * `CentralBankOfAzerbaijanImporter` (AZ). This v1.2 BE/HR/LU/MT batch
     * added four more, XLSX-sourced importers -- `NationalBankOfBelgiumImporter`
     * (BE), `CroatianNationalBankImporter` (HR),
     * `LuxembourgBankersAssociationImporter` (LU) and
     * `CentralBankOfMaltaImporter` (MT). This v1.2 HU/NO/GE batch adds three
     * more, also XLSX-sourced -- `MagyarNemzetiBankImporter` (HU),
     * `BitsNorwayImporter` (NO) and `NationalBankOfGeorgiaImporter` (GE).
     * This v1.2 IL/UA/KZ batch adds three more, JSON-sourced importers --
     * `BankOfIsraelImporter` (IL), `NationalBankOfUkraineImporter` (UA) and
     * `NationalBankOfKazakhstanImporter` (KZ). The v1.2 BR/LI batch adds two
     * more -- `BrazilianCentralBankImporter` (BR) and `LiechtensteinImporter`
     * (LI). This v1.2 EPC SEPA Register batch registers `EpcRegisterImporter`
     * five times -- once each for GB, GI, IE, LV and RO. So with no
     * `--country`/`--source` selection, `iban:update` now lists all thirty
     * alongside the v1.0 licensing notices instead of the old "nothing
     * bundled yet" deferral.
     */
    public function testUpdatePrintsLicenseNoticesAndListsTheBundledImportersAndExitsSuccess(): void
    {
        [$exit, $output] = $this->runSpark(['iban:update']);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('SWIFT IBAN Registry', $output);
        self::assertStringContainsString('SWIFT BIC Directory', $output);
        self::assertStringContainsString('National lists require per-source attribution.', $output);
        self::assertStringContainsString('Registered importers: 33', $output);
        self::assertStringContainsString('oenb', $output);
        self::assertStringContainsString('bundesbank', $output);
        self::assertStringContainsString('six', $output);
        self::assertStringContainsString('betaalvereniging', $output);
        self::assertStringContainsString('bde', $output);
        self::assertStringContainsString('cnb', $output);
        self::assertStringContainsString('hba', $output);
        self::assertStringContainsString('bsi', $output);
        self::assertStringContainsString('nbs', $output);
        self::assertStringContainsString('bnb', $output);
        self::assertStringContainsString('bnm', $output);
        self::assertStringContainsString('nbp', $output);
        self::assertStringContainsString('cbar', $output);
        self::assertStringContainsString('nbb', $output);
        self::assertStringContainsString('hnb', $output);
        self::assertStringContainsString('abbl', $output);
        self::assertStringContainsString('cbm', $output);
        self::assertStringContainsString('mnb', $output);
        self::assertStringContainsString('bits', $output);
        self::assertStringContainsString('nbg', $output);
        self::assertStringContainsString('boi', $output);
        self::assertStringContainsString('nbu', $output);
        self::assertStringContainsString('nbk', $output);
        self::assertStringContainsString('bcb', $output);
        self::assertStringContainsString('epc', $output);
        self::assertStringContainsString('bankinfrastruktur', $output);
        self::assertStringContainsString('regafi', $output);
        self::assertStringContainsString('AT', $output);
        self::assertStringContainsString('DE', $output);
        self::assertStringContainsString('CH', $output);
        self::assertStringContainsString('NL', $output);
        self::assertStringContainsString('ES', $output);
        self::assertStringContainsString('CZ', $output);
        self::assertStringContainsString('GR', $output);
        self::assertStringContainsString('SI', $output);
        self::assertStringContainsString('SK', $output);
        self::assertStringContainsString('BG', $output);
        self::assertStringContainsString('MD', $output);
        self::assertStringContainsString('PL', $output);
        self::assertStringContainsString('AZ', $output);
        self::assertStringContainsString('BE', $output);
        self::assertStringContainsString('HR', $output);
        self::assertStringContainsString('LU', $output);
        self::assertStringContainsString('MT', $output);
        self::assertStringContainsString('HU', $output);
        self::assertStringContainsString('NO', $output);
        self::assertStringContainsString('GE', $output);
        self::assertStringContainsString('IL', $output);
        self::assertStringContainsString('UA', $output);
        self::assertStringContainsString('KZ', $output);
        self::assertStringContainsString('LI', $output);
        self::assertStringContainsString('BR', $output);
        self::assertStringContainsString('GB', $output);
        self::assertStringContainsString('GI', $output);
        self::assertStringContainsString('IE', $output);
        self::assertStringContainsString('LV', $output);
        self::assertStringContainsString('RO', $output);
        self::assertStringContainsString('SE', $output);
        self::assertStringContainsString('FR', $output);
        self::assertStringContainsString('MC', $output);
        self::assertStringContainsString('Select one with --country=/--source= to run it', $output);
    }

    public function testUpdateAcceptsDryRunAndCountryOptionsWithoutErrorAndReportsNoMatch(): void
    {
        // 'DK' matches none of the bundled importers -- Denmark is tier D in
        // the SEPA-coverage initiative (no open, machine-readable source), so
        // it is deliberately never bundled. This keeps the graceful "no
        // match" branch and, crucially, never reaches the network/file fetch.
        // (FR/MC used to sit here but are now bundled via RegafiImporter.)
        [$exit, $output] = $this->runSpark(['iban:update', '--dry-run', '--country', 'DK']);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('SWIFT IBAN Registry', $output);
        self::assertStringContainsString('No bundled importer matches that selection.', $output);
        // A selection was made, so this is the "no match" branch, not the
        // no-selection listing -- the registry-size line must NOT appear.
        self::assertStringNotContainsString('Registered importers:', $output);
    }

    public function testUpdateWithSourceOptionAloneAlsoReportsNoMatchGracefully(): void
    {
        // A source id that isn't bundled by any registered importer -- kept
        // distinct from 'oenb'/'bundesbank' (V-7a) so this stays a genuine
        // no-match case instead of triggering a live network fetch.
        [$exit, $output] = $this->runSpark(['iban:update', '--source', 'not-a-real-source']);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('No bundled importer matches that selection.', $output);
    }

    /**
     * V-7a acceptance criterion: `iban:update --source=oenb --country=AT
     * --file=<fixture>` actually imports (offline, no network) and reports
     * non-zero `imported`. The DB-level assertions (rows/provenance) live in
     * `tests/Import/ImportRunnerImportersTest.php`; this test only proves
     * the command wiring (selection + `--file` + report printing) end-to-end.
     */
    public function testUpdateWithFileOptionImportsTheOenbFixtureAndReportsImportedCount(): void
    {
        [$exit, $output] = $this->runSpark([
            'iban:update', '--source', 'oenb', '--country', 'AT', '--file', self::OENB_FIXTURE,
        ]);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('[AT/oenb] fetched=2 imported=2 skipped=0', $output);
        self::assertStringContainsString('CC-BY-4.0 (OeNB)', $output);
    }

    public function testUpdateWithFileOptionImportsTheBundesbankFixtureAndReportsImportedCount(): void
    {
        [$exit, $output] = $this->runSpark([
            'iban:update', '--source', 'bundesbank', '--country', 'DE', '--file', self::BUNDESBANK_FIXTURE,
        ]);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('[DE/bundesbank] fetched=3 imported=3 skipped=0', $output);
        self::assertStringContainsString('Deutsche Bundesbank', $output);
    }

    /**
     * V-7b acceptance criterion: `iban:update --source=six --country=CH
     * --file=<fixture>` actually imports (offline, no network) and reports
     * non-zero `imported`.
     */
    public function testUpdateWithFileOptionImportsTheSixFixtureAndReportsImportedCount(): void
    {
        [$exit, $output] = $this->runSpark([
            'iban:update', '--source', 'six', '--country', 'CH', '--file', self::SIX_FIXTURE,
        ]);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('[CH/six] fetched=2 imported=2 skipped=0', $output);
        self::assertStringContainsString('SIX Interbank Clearing (free use)', $output);
    }

    public function testUpdateWithFileOptionImportsTheBetaalverenigingFixtureAndReportsImportedCount(): void
    {
        [$exit, $output] = $this->runSpark([
            'iban:update', '--source', 'betaalvereniging', '--country', 'NL', '--file', self::BETAALVERENIGING_FIXTURE,
        ]);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('[NL/betaalvereniging] fetched=3 imported=3 skipped=0', $output);
        self::assertStringContainsString('Betaalvereniging Nederland (see terms)', $output);
    }

    public function testUpdateWithFileOptionImportsTheBdeFixtureAndReportsImportedCount(): void
    {
        [$exit, $output] = $this->runSpark([
            'iban:update', '--source', 'bde', '--country', 'ES', '--file', self::BDE_FIXTURE,
        ]);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('[ES/bde] fetched=3 imported=3 skipped=0', $output);
        self::assertStringContainsString('Banco de España', $output);
    }

    public function testUpdateWithFileOptionAndDryRunDoesNotWriteToTheBanksTable(): void
    {
        [$exit, $output] = $this->runSpark([
            'iban:update', '--source', 'oenb', '--country', 'AT', '--file', self::OENB_FIXTURE, '--dry-run',
        ]);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('(dry-run — nothing written)', $output);
        self::assertSame(0, $this->db->table('banks')->countAllResults());
    }

    // -- iban:update --all (v1.2) ------------------------------------------

    // The `--all` run loop is exercised against an INJECTED registry of fake
    // importers (via `UpdateCommand::$registry`) rather than the 30 bundled
    // ones. The bundled importers fetch their source live over the network --
    // and several official sources ARE reachable from CI and return large
    // payloads -- so a real `--all` run in the test suite would be
    // non-deterministic, slow, and memory-heavy. Fakes make the selection,
    // per-importer failure isolation, and aggregate-summary assertions exact
    // and offline. (`ImporterRegistry`'s default catalog of 30 is covered by
    // `tests/Import/ImporterRegistryTest.php`; `--all` selects it via
    // `$registry->all()`.)

    /**
     * Builds an in-memory {@see ImporterRegistry} pre-populated ONLY with the
     * given fakes (its default catalog is suppressed), so `--all` selects
     * exactly these and nothing that touches the network.
     */
    private function registryOf(ImporterInterface ...$importers): ImporterRegistry
    {
        $registry = new class () extends ImporterRegistry {
            protected function registerDefaults(): void
            {
                // Intentionally empty: keep the catalog to injected fakes only.
            }
        };

        foreach ($importers as $importer) {
            $registry->register($importer);
        }

        return $registry;
    }

    /**
     * A framework-free fake {@see ImporterInterface}. When `$throws` is true
     * its `rows()` raises, proving one importer failing doesn't abort the
     * rest of an `--all` run; otherwise it yields `$rows` verbatim.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function fakeImporter(string $country, string $source, bool $throws = false, array $rows = []): ImporterInterface
    {
        return new class ($country, $source, $throws, $rows) implements ImporterInterface {
            /** @param list<array<string, mixed>> $rows */
            public function __construct(
                private readonly string $country,
                private readonly string $source,
                private readonly bool $throws,
                private readonly array $rows,
            ) {
            }

            public function countryCode(): string
            {
                return $this->country;
            }

            public function sourceId(): string
            {
                return $this->source;
            }

            public function sourceName(): string
            {
                return 'Fake ' . $this->source;
            }

            public function license(): string
            {
                return 'test-fixture (no license)';
            }

            public function sourceUrl(): string
            {
                return 'https://example.test/' . $this->source;
            }

            /**
             * @return iterable<array<string, mixed>>
             */
            public function rows(?string $localFile = null): iterable
            {
                if ($this->throws) {
                    throw new \RuntimeException('simulated source failure');
                }

                return $this->rows;
            }
        };
    }

    /**
     * Runs `iban:update` with an injected registry, mirroring `runSpark()`'s
     * argv seeding + `CLI::init()` reparse but constructing the command
     * directly so `UpdateCommand::$registry` (the test seam) can be set --
     * `service('commands')->run()` always news the command with the default
     * registry, giving no injection point.
     *
     * @param list<string> $argv
     *
     * @return array{0: int, 1: string} [exitCode, captured STDOUT/STDERR]
     */
    private function runUpdateWith(array $argv, ImporterRegistry $registry): array
    {
        service('superglobals')->setServer('argv', array_merge(['spark'], $argv));
        CLI::init();

        $params = array_merge(CLI::getSegments(), CLI::getOptions());
        array_shift($params);

        $command           = new UpdateCommand(service('logger'), service('commands'));
        $command->registry = $registry;

        $this->resetStreamFilterBuffer();

        $exitCode = $command->run($params);

        return [$exitCode, $this->getStreamFilterBuffer()];
    }

    /**
     * `--all --dry-run`: every selected importer runs, an aggregate summary
     * reports how many produced rows / were empty / failed, and (dry-run)
     * nothing is written. One fake throws -- the run still completes and the
     * other two are reported, proving per-importer failure isolation.
     */
    public function testUpdateAllDryRunRunsEveryImporterIsolatesFailuresAndPrintsAggregateSummary(): void
    {
        $registry = $this->registryOf(
            $this->fakeImporter('AT', 'fa', rows: [['bank_code' => '0001', 'name' => 'Fake AT Bank']]),
            $this->fakeImporter('DE', 'fb'),           // no rows -> empty
            $this->fakeImporter('CH', 'fc', throws: true),
        );

        [$exit, $output] = $this->runUpdateWith(['iban:update', '--all', '--dry-run'], $registry);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('SWIFT IBAN Registry', $output);
        self::assertStringContainsString('[AT/fa]', $output);
        self::assertStringContainsString('[DE/fb]', $output);
        // The throwing importer is reported as a failure, not fatal.
        self::assertStringContainsString('[CH/fc] failed: simulated source failure', $output);
        self::assertStringContainsString(
            'Ran 3 importers: 1 with rows, 1 empty, 1 failed. (dry-run)',
            $output,
        );
        // --dry-run: the AT fake's row is counted but never written.
        self::assertSame(0, $this->db->table('banks')->countAllResults());
    }

    /**
     * Without `--dry-run`, `--all` really writes each surviving importer's
     * rows; the throwing importer in the middle neither aborts the run nor
     * prevents the importer after it from writing.
     */
    public function testUpdateAllWritesSurvivingImportersRowsDespiteAFailureInTheMiddle(): void
    {
        $registry = $this->registryOf(
            $this->fakeImporter('AT', 'fa', rows: [['bank_code' => '0001', 'name' => 'Fake AT Bank']]),
            $this->fakeImporter('DE', 'fb', throws: true),
            $this->fakeImporter('CH', 'fc', rows: [['bank_code' => '0002', 'name' => 'Fake CH Bank']]),
        );

        [$exit, $output] = $this->runUpdateWith(['iban:update', '--all'], $registry);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('[DE/fb] failed: simulated source failure', $output);
        self::assertStringContainsString(
            'Ran 3 importers: 2 with rows, 0 empty, 1 failed.',
            $output,
        );
        self::assertStringNotContainsString('(dry-run)', $output);
        // Both surviving importers wrote their single row.
        self::assertSame(2, $this->db->table('banks')->countAllResults());
    }

    /**
     * `--all` combined with `--country` narrows the selection to
     * {@see ImporterRegistry::forCountry()} instead of running every
     * importer; `--source` is ignored under `--all`.
     */
    public function testUpdateAllWithCountryNarrowsToThatCountrysImportersOnly(): void
    {
        $registry = $this->registryOf(
            $this->fakeImporter('GB', 'fgb', rows: [['bank_code' => 'AAAA', 'name' => 'Fake GB Bank']]),
            $this->fakeImporter('AT', 'fat', rows: [['bank_code' => '0001', 'name' => 'Fake AT Bank']]),
        );

        [$exit, $output] = $this->runUpdateWith(['iban:update', '--all', '--country', 'GB', '--dry-run'], $registry);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('[GB/fgb]', $output);
        self::assertStringNotContainsString('[AT/fat]', $output);
        self::assertStringContainsString(
            'Ran 1 importers: 1 with rows, 0 empty, 0 failed. (dry-run)',
            $output,
        );
    }

    /**
     * `--all` cannot be combined with `--file`: a single local file can't
     * feed multiple importers, so the command errors out (before any fetch)
     * and exits non-zero. Uses the real dispatch path -- no fetch occurs.
     */
    public function testUpdateAllCannotBeCombinedWithFileAndExitsError(): void
    {
        [$exit, $output] = $this->runSpark(['iban:update', '--all', '--file', '/x']);

        self::assertSame(EXIT_ERROR, $exit);
        self::assertStringContainsString(
            '--all cannot be combined with --file (a single local file cannot feed multiple importers).',
            $output,
        );
    }

    /**
     * `--all` narrowed to a country with no registered importer hits the
     * graceful "no match" notice (and never fetches).
     */
    public function testUpdateAllWithCountryHavingNoImporterReportsNoMatch(): void
    {
        [$exit, $output] = $this->runUpdateWith(
            ['iban:update', '--all', '--country', 'ZZ'],
            $this->registryOf($this->fakeImporter('AT', 'fa')),
        );

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('No bundled importer matches that selection.', $output);
    }

    // -- iban:bic ----------------------------------------------------------

    public function testBicCommandValidBicPrintsFieldsAndExitsSuccess(): void
    {
        [$exit, $output] = $this->runSpark(['iban:bic', 'CAIXESBBXXX']);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('CAIXESBBXXX', $output);
        self::assertStringContainsString('ES', $output);
        // Empty DB / NullProvider => structural fields only.
        self::assertStringContainsString('no provider data', $output);
    }

    public function testBicCommandJsonOutputForAValidBic(): void
    {
        [$exit, $output] = $this->runSpark(['iban:bic', 'CAIXESBBXXX', '--json']);

        self::assertSame(EXIT_SUCCESS, $exit);

        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertTrue($decoded['valid']);
        self::assertSame('CAIX', $decoded['institutionCode']);
        self::assertSame('ES', $decoded['countryCode']);
        self::assertSame('XXX', $decoded['branchCode']);
        self::assertFalse($decoded['resolved']);
        self::assertNull($decoded['bankName']);
    }

    public function testBicCommandInvalidBicPrintsViolationAndExitsError(): void
    {
        [$exit, $output] = $this->runSpark(['iban:bic', 'nonsense']);

        self::assertSame(EXIT_ERROR, $exit);
        self::assertStringContainsString('INVALID', $output);
        self::assertStringContainsString(ViolationCode::BicUnknownCountry->value, $output);
    }

    public function testBicCommandInvalidBicJsonReportsValidFalseAndExitsError(): void
    {
        [$exit, $output] = $this->runSpark(['iban:bic', 'ZZ', '--json']);

        self::assertSame(EXIT_ERROR, $exit);

        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertFalse($decoded['valid']);
        self::assertSame(ViolationCode::BicBadLength->value, $decoded['violation']['code']);
    }

    // -- iban:validate --bic (combined modes) ------------------------------

    public function testValidateWithBicOnlyValidatesJustTheBic(): void
    {
        [$exit, $output] = $this->runSpark(['iban:validate', '--bic', 'CAIXESBBXXX']);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('VALID', $output);
    }

    public function testValidateWithInvalidBicOnlyExitsError(): void
    {
        [$exit, $output] = $this->runSpark(['iban:validate', '--bic', 'ZZ']);

        self::assertSame(EXIT_ERROR, $exit);
        self::assertStringContainsString(ViolationCode::BicBadLength->value, $output);
    }

    public function testValidateWithIbanAndBothCoherentExitsSuccess(): void
    {
        [$exit, $output] = $this->runSpark(['iban:validate', self::VALID_ES_IBAN, '--bic', 'CAIXESBB']);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('VALID', $output);
    }

    public function testValidateWithIbanAndBicCountryMismatchExitsError(): void
    {
        [$exit, $output] = $this->runSpark(['iban:validate', self::VALID_ES_IBAN, '--bic', 'DEUTDEFF']);

        self::assertSame(EXIT_ERROR, $exit);
        self::assertStringContainsString(ViolationCode::BicIbanCountryMismatch->value, $output);
    }

    public function testValidateCombinedJsonOutputHasValidBoolAndViolationsArray(): void
    {
        [$exit, $output] = $this->runSpark(['iban:validate', self::VALID_ES_IBAN, '--bic', 'DEUTDEFF', '--json']);

        self::assertSame(EXIT_ERROR, $exit);

        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertFalse($decoded['valid']);
        self::assertIsArray($decoded['violations']);
        self::assertSame(ViolationCode::BicIbanCountryMismatch->value, $decoded['violations'][0]['code']);
    }

    public function testValidateCombinedJsonForBothValidHasEmptyViolations(): void
    {
        [$exit, $output] = $this->runSpark(['iban:validate', self::VALID_ES_IBAN, '--bic', 'CAIXESBB', '--json']);

        self::assertSame(EXIT_SUCCESS, $exit);

        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertTrue($decoded['valid']);
        self::assertSame([], $decoded['violations']);
    }

    /**
     * Guard against a regression in the byte-identical IBAN-only path: without
     * `--bic`, the single-IBAN JSON shape (`violation`, singular, nullable)
     * must be exactly as before — NOT the combined `violations` array shape.
     */
    public function testValidateWithoutBicKeepsTheSingularViolationJsonShape(): void
    {
        [$exit, $output] = $this->runSpark(['iban:validate', self::VALID_ES_IBAN, '--json']);

        self::assertSame(EXIT_SUCCESS, $exit);

        $decoded = json_decode($output, true);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('violation', $decoded);
        self::assertArrayNotHasKey('violations', $decoded);
    }

    // -- Discovery -----------------------------------------------------------

    public function testAllCommandsAreDiscoveredUnderTheIbanGroup(): void
    {
        $commands = service('commands')->getCommands();

        foreach ([
            'iban:validate' => ValidateCommand::class,
            'iban:parse'    => ParseCommand::class,
            'iban:resolve'  => ResolveCommand::class,
            'iban:update'   => UpdateCommand::class,
            'iban:publish'  => PublishCommand::class,
            'iban:bic'      => BicCommand::class,
        ] as $name => $class) {
            self::assertArrayHasKey($name, $commands);
            self::assertSame($class, $commands[$name]['class']);
            self::assertSame('IBAN', $commands[$name]['group']);
        }
    }
}
