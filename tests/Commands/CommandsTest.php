<?php

declare(strict_types=1);

namespace Tests\Commands;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\StreamFilterTrait;
use Daycry\Iban\Commands\ParseCommand;
use Daycry\Iban\Commands\ResolveCommand;
use Daycry\Iban\Commands\UpdateCommand;
use Daycry\Iban\Commands\ValidateCommand;
use Daycry\Iban\Config\Iban as IbanConfig;
use Daycry\Iban\Core\Mod97;
use Daycry\Iban\Enums\ViolationCode;

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
     * `ImporterRegistry::registerDefaults()` (empty since V-6). V-7b adds
     * three more -- `SixImporter` (CH), `BetaalverenigingImporter` (NL) and
     * `BancoDeEspanaImporter` (ES). So with no `--country`/`--source`
     * selection, `iban:update` now lists all five alongside the v1.0
     * licensing notices instead of the old "nothing bundled yet" deferral.
     */
    public function testUpdatePrintsLicenseNoticesAndListsTheBundledImportersAndExitsSuccess(): void
    {
        [$exit, $output] = $this->runSpark(['iban:update']);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertStringContainsString('SWIFT IBAN Registry', $output);
        self::assertStringContainsString('SWIFT BIC Directory', $output);
        self::assertStringContainsString('National lists require per-source attribution.', $output);
        self::assertStringContainsString('Registered importers: 5', $output);
        self::assertStringContainsString('oenb', $output);
        self::assertStringContainsString('bundesbank', $output);
        self::assertStringContainsString('six', $output);
        self::assertStringContainsString('betaalvereniging', $output);
        self::assertStringContainsString('bde', $output);
        self::assertStringContainsString('AT', $output);
        self::assertStringContainsString('DE', $output);
        self::assertStringContainsString('CH', $output);
        self::assertStringContainsString('NL', $output);
        self::assertStringContainsString('ES', $output);
        self::assertStringContainsString('Select one with --country=/--source= to run it', $output);
    }

    public function testUpdateAcceptsDryRunAndCountryOptionsWithoutErrorAndReportsNoMatch(): void
    {
        // 'FR' matches none of the 5 bundled importers (AT/DE/CH/NL/ES), so
        // this stays the graceful "no match" branch -- and, crucially,
        // never reaches the network/file fetch.
        [$exit, $output] = $this->runSpark(['iban:update', '--dry-run', '--country', 'FR']);

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

    // -- Discovery -----------------------------------------------------------

    public function testAllFourCommandsAreDiscoveredUnderTheIbanGroup(): void
    {
        $commands = service('commands')->getCommands();

        foreach ([
            'iban:validate' => ValidateCommand::class,
            'iban:parse'    => ParseCommand::class,
            'iban:resolve'  => ResolveCommand::class,
            'iban:update'   => UpdateCommand::class,
        ] as $name => $class) {
            self::assertArrayHasKey($name, $commands);
            self::assertSame($class, $commands[$name]['class']);
            self::assertSame('IBAN', $commands[$name]['group']);
        }
    }
}
