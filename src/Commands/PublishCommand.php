<?php

declare(strict_types=1);

namespace Daycry\Iban\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * `spark iban:publish [--force]`
 *
 * Publishes the package's {@see \Daycry\Iban\Config\Iban} into the consuming
 * app as `app/Config/Iban.php` -- the same idiomatic CI4 "publish a config"
 * pattern used by packages like `daycry/auth`/Shield. The published class
 * lives in `namespace Config` and `extends \Daycry\Iban\Config\Iban`, so
 * CI4's `config()`/`service('iban')` resolution (which prefers the app's
 * `Config\` namespace over the package's) picks it up automatically, and it
 * stays forward-compatible: any property the app doesn't override keeps
 * inheriting the package's default (including new ones added in a later
 * version), and PHPStan/IDEs see a real, type-checked subclass rather than a
 * hand-copied duplicate that can drift out of sync.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class PublishCommand extends BaseCommand
{
    protected $group       = 'IBAN';
    protected $name        = 'iban:publish';
    protected $description = 'Publish the Iban config into the app (app/Config/Iban.php) for customization.';
    protected $usage       = 'iban:publish [--force]';

    /** @var array<string, string> */
    protected $options = [
        '--force' => 'Overwrite an existing app/Config/Iban.php.',
    ];

    /**
     * Injection seam for the publish target path. Production leaves this
     * `null` and {@see self::run()} defaults to `APPPATH . 'Config/Iban.php'`;
     * tests assign a temp file path so the publish can be exercised
     * end-to-end (write, existing-file guard, `--force` overwrite) without
     * touching a real app's `Config/` directory.
     */
    public ?string $targetPath = null;

    public function run(array $params): int
    {
        $target = $this->targetPath ?? APPPATH . 'Config/Iban.php';
        $force  = CLI::getOption('force') !== null || array_key_exists('force', $params);

        if (is_file($target) && ! $force) {
            CLI::error('app/Config/Iban.php already exists — use --force to overwrite.');

            return EXIT_ERROR;
        }

        $content = $this->buildPublishedContent();

        if ($content === null) {
            return EXIT_ERROR;
        }

        $directory = dirname($target);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            CLI::error('Could not create directory: ' . $directory);

            return EXIT_ERROR;
        }

        if (file_put_contents($target, $content) === false) {
            CLI::error('Could not write to ' . $target);

            return EXIT_ERROR;
        }

        CLI::write('Published Iban config to ' . clean_path($target), 'green');

        return EXIT_SUCCESS;
    }

    /**
     * Reads the package's real `src/Config/Iban.php` at runtime and rewrites
     * exactly three lines so the published copy extends it instead of
     * duplicating it: the namespace, the `BaseConfig` import (swapped for the
     * package config as `BaseIban`), and the class declaration. Everything
     * else -- docblocks, all 6 properties and their defaults -- is kept
     * verbatim, so the published file stays a thin, forward-compatible
     * subclass.
     *
     * Each replacement is asserted to have actually matched; if the source
     * layout ever changes in a way that breaks one of these three exact
     * strings, this bails out with an error instead of writing a broken
     * (non-extending, or still-CI4-BaseConfig-based) config into the app.
     */
    private function buildPublishedContent(): ?string
    {
        $source = dirname(__DIR__) . '/Config/Iban.php';

        $contents = file_get_contents($source);

        if ($contents === false) {
            CLI::error('Could not read the package config source at ' . $source);

            return null;
        }

        $replacements = [
            'namespace Daycry\Iban\Config;' => 'namespace Config;',
            'use CodeIgniter\Config\BaseConfig;' => 'use Daycry\Iban\Config\Iban as BaseIban;',
            'class Iban extends BaseConfig' => 'class Iban extends BaseIban',
        ];

        foreach ($replacements as $search => $replace) {
            $count    = 0;
            $contents = str_replace($search, $replace, $contents, $count);

            if ($count === 0) {
                CLI::error(
                    'Unexpected source layout in src/Config/Iban.php: could not find "' . $search . '". '
                    . 'Not publishing a possibly-broken config.',
                );

                return null;
            }
        }

        return $contents;
    }
}
