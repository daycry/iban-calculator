<?php

declare(strict_types=1);

namespace Daycry\Iban\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * `spark iban:update [--source=<source>] [--country=<country>] [--dry-run]`
 *
 * Documented no-op in v1.0: this package ships zero bank-data importers.
 * `SWIFT IBAN Registry` (non-commercial/no-derivatives) and `SWIFT BIC
 * Directory` / SwiftRef (proprietary) cannot be bundled, and national lists
 * each carry their own attribution terms -- so v1.0 ships an empty `banks`
 * table plus this command as a placeholder that documents the situation
 * instead of silently doing nothing. Real importers are deferred to v1.1.
 *
 * Accepts `--source`, `--country`, and `--dry-run` for forward
 * compatibility with the (future) real importer command -- none of them
 * change this command's behavior today.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class UpdateCommand extends BaseCommand
{
    protected $group       = 'IBAN';
    protected $name        = 'iban:update';
    protected $description = 'Documented no-op in v1.0: prints bank-data licensing notices; real importers are deferred to v1.1.';
    protected $usage       = 'iban:update [--source=<source>] [--country=<country>] [--dry-run]';

    /** @var array<string, string> */
    protected $options = [
        '--source'  => 'Restrict the (future) import to a specific data source. No-op in v1.0.',
        '--country' => 'Restrict the (future) import to a specific country. No-op in v1.0.',
        '--dry-run' => 'Preview without writing. No-op in v1.0.',
    ];

    public function run(array $params): int
    {
        CLI::write('SWIFT IBAN Registry is non-commercial/no-derivatives (not bundled).', 'yellow');
        CLI::write('SWIFT BIC Directory (SwiftRef) is proprietary (not bundled).', 'yellow');
        CLI::write('National lists require per-source attribution.', 'yellow');

        CLI::write('Registered importers: 0', 'yellow');

        CLI::write(
            'No importers bundled — bank-data import is deferred to v1.1. This command is a documented no-op in v1.0.',
            'yellow',
        );

        return EXIT_SUCCESS;
    }
}
