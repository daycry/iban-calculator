<?php

declare(strict_types=1);

namespace Daycry\Iban\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Daycry\Iban\Config\Iban as IbanConfig;
use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\ImporterRegistry;
use Daycry\Iban\Import\ImportReport;
use Daycry\Iban\Import\ImportRunner;
use Daycry\Iban\Models\BankModel;

/**
 * `spark iban:update [--all] [--country=<cc>] [--source=<id>] [--dry-run] [--file=<path>]`
 *
 * Functional as of v1.1 (V-6): builds the default {@see ImporterRegistry},
 * selects registered importers by `--country`/`--source`, and runs each
 * selected one through {@see ImportRunner} against the `banks` table
 * (`Config\Iban::$table`/`$dbGroup`), printing its {@see ImportReport} plus
 * the source's license/attribution notice.
 *
 * With no `--country`/`--source`/`--all` given at all, it only lists the
 * registered importers (via {@see ImporterRegistry::sources()}) instead of
 * running anything -- pick one explicitly to actually import.
 *
 * As of v1.1's V-7a, `ImporterRegistry` bundles official-source importers
 * for OeNB (AT) and Bundesbank (DE) by default, so a bare `iban:update`
 * lists them and `--country`/`--source` runs one. The graceful "no bundled
 * importer matches that selection" notice below is now only reached when a
 * `--country`/`--source` selection doesn't match any registered importer --
 * covered by `tests/Commands/CommandsTest.php`.
 *
 * v1.2 adds `--all`: run EVERY registered importer in one invocation
 * (`$registry->all()`, or `$registry->forCountry()` when `--country` is
 * combined with it -- `--source` is ignored under `--all`). It takes
 * precedence over the "no selection -> list" branch, so `iban:update --all`
 * runs rather than lists. It cannot be combined with `--file` (a single
 * local file cannot feed multiple importers) -- that combination errors out
 * immediately. Each importer's run is isolated in its own try/catch so one
 * failure (an unreachable portal, a DB hiccup, ...) doesn't abort the rest;
 * a final aggregate summary line reports how many importers produced rows,
 * came back empty, or failed.
 *
 * v1.0's licensing notices (SWIFT IBAN Registry / SWIFT BIC Directory /
 * per-source national list attribution) are always printed first, since
 * they apply regardless of which importer -- if any -- ends up running.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class UpdateCommand extends BaseCommand
{
    protected $group       = 'IBAN';
    protected $name        = 'iban:update';
    protected $description = 'Lists/runs bank-data importers against the `banks` table, printing licensing notices and an import report.';
    protected $usage       = 'iban:update [--all] [--country=<cc>] [--source=<id>] [--dry-run] [--file=<path>]';

    /** @var array<string, string> */
    protected $options = [
        '--all'     => 'Run every bundled importer (all countries/sources). Fetches live; combine with --dry-run to preview. Cannot be combined with --file.',
        '--country' => 'Restrict to importers for this ISO 3166-1 alpha-2 country code (e.g. AT).',
        '--source'  => 'Restrict to the importer with this source id (e.g. oenb).',
        '--dry-run' => 'Preview: count what would be imported without writing to the `banks` table.',
        '--file'    => 'Import offline from this local file instead of fetching from the source live.',
    ];

    /**
     * Injection seam for the importer catalog. Production leaves this `null`
     * and {@see self::run()} constructs the default {@see ImporterRegistry};
     * tests assign a controlled registry so the `--all` run loop can be
     * exercised deterministically (selection, per-importer failure isolation,
     * aggregate summary) without any live network fetch.
     */
    public ?ImporterRegistry $registry = null;

    public function run(array $params): int
    {
        CLI::write('SWIFT IBAN Registry is non-commercial/no-derivatives (not bundled).', 'yellow');
        CLI::write('SWIFT BIC Directory (SwiftRef) is proprietary (not bundled).', 'yellow');
        CLI::write('National lists require per-source attribution.', 'yellow');

        $registry = $this->registry ?? new ImporterRegistry();

        $all           = (bool) CLI::getOption('all');
        $countryOption = CLI::getOption('country');
        $sourceOption  = CLI::getOption('source');
        $country       = is_string($countryOption) ? $countryOption : null;
        $source        = is_string($sourceOption) ? $sourceOption : null;

        $dryRun     = (bool) CLI::getOption('dry-run');
        $fileOption = CLI::getOption('file');
        $file       = is_string($fileOption) ? $fileOption : null;

        if ($all && $file !== null) {
            CLI::error('--all cannot be combined with --file (a single local file cannot feed multiple importers).');

            return EXIT_ERROR;
        }

        if ($all) {
            $matches = $country !== null ? $registry->forCountry($country) : $registry->all();

            return $this->runAll($matches, $dryRun);
        }

        if ($country === null && $source === null) {
            $this->listImporters($registry);

            return EXIT_SUCCESS;
        }

        $matches = self::filterImporters($registry->all(), $country, $source);

        if ($matches === []) {
            CLI::write(
                'No bundled importer matches that selection.',
                'yellow',
            );

            return EXIT_SUCCESS;
        }

        $config = config(IbanConfig::class);
        $runner = new ImportRunner();

        foreach ($matches as $importer) {
            $model  = new BankModel($config->table, $config->dbGroup);
            $report = $runner->run($importer, $model, $dryRun, $file);

            $this->printReport($importer, $report);
        }

        return EXIT_SUCCESS;
    }

    /**
     * `--all` path: runs every importer in `$importers`, isolating each
     * behind its own try/catch so one failure (an unreachable portal, a DB
     * hiccup, ...) doesn't abort the rest, then prints an aggregate summary.
     *
     * @param list<ImporterInterface> $importers
     */
    private function runAll(array $importers, bool $dryRun): int
    {
        if ($importers === []) {
            CLI::write(
                'No bundled importer matches that selection.',
                'yellow',
            );

            return EXIT_SUCCESS;
        }

        $config = config(IbanConfig::class);
        $runner = new ImportRunner();

        $withRows = 0;
        $empty    = 0;
        $failed   = 0;

        foreach ($importers as $importer) {
            try {
                $model  = new BankModel($config->table, $config->dbGroup);
                $report = $runner->run($importer, $model, $dryRun);

                $this->printReport($importer, $report);

                if ($report->imported > 0) {
                    $withRows++;
                } else {
                    $empty++;
                }
            } catch (\Throwable $e) {
                $failed++;

                CLI::error(sprintf(
                    '[%s/%s] failed: %s',
                    $importer->countryCode(),
                    $importer->sourceId(),
                    $e->getMessage(),
                ));
            }
        }

        CLI::write(sprintf(
            'Ran %d importers: %d with rows, %d empty, %d failed.%s',
            count($importers),
            $withRows,
            $empty,
            $failed,
            $dryRun ? ' (dry-run)' : '',
        ), 'green');

        return EXIT_SUCCESS;
    }

    private function listImporters(ImporterRegistry $registry): void
    {
        $sources = $registry->sources();

        CLI::write('Registered importers: ' . count($sources), 'yellow');

        if ($sources === []) {
            CLI::write(
                'No bundled importers registered.',
                'yellow',
            );

            return;
        }

        $rows = [];

        foreach ($sources as $entry) {
            $rows[] = [$entry['country'], $entry['source'], $entry['name'], $entry['license']];
        }

        CLI::table($rows, ['Country', 'Source', 'Name', 'License']);
        CLI::write('Select one with --country=/--source= to run it (add --dry-run to preview).', 'yellow');
    }

    private function printReport(ImporterInterface $importer, ImportReport $report): void
    {
        $label = $report->dryRun ? ' (dry-run — nothing written)' : '';

        CLI::write(sprintf(
            '[%s/%s] fetched=%d imported=%d skipped=%d%s',
            $report->countryCode,
            $report->sourceId,
            $report->fetched,
            $report->imported,
            $report->skipped,
            $label,
        ), 'green');

        foreach ($report->messages as $message) {
            CLI::write('  ' . $message, 'yellow');
        }

        CLI::write(sprintf(
            'Source: %s — %s (%s)',
            $importer->sourceName(),
            $importer->sourceUrl(),
            $importer->license(),
        ), 'yellow');
    }

    /**
     * @param list<ImporterInterface> $importers
     *
     * @return list<ImporterInterface>
     */
    private static function filterImporters(array $importers, ?string $country, ?string $source): array
    {
        return array_values(array_filter(
            $importers,
            static function (ImporterInterface $importer) use ($country, $source): bool {
                if ($country !== null && strcasecmp($importer->countryCode(), $country) !== 0) {
                    return false;
                }

                if ($source !== null && strcasecmp($importer->sourceId(), $source) !== 0) {
                    return false;
                }

                return true;
            },
        ));
    }
}
