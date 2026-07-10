<?php

declare(strict_types=1);

/**
 * Thin, portable wrapper around `phpstan analyse` used by the `composer analyze` script.
 *
 * Why this exists: `src/` is currently a greenfield PSR-4 skeleton (Phase 1 of the
 * roadmap) — only `.gitkeep` placeholders exist under `src/Core/` and `src/Contracts/`,
 * no `.php` files yet. PHPStan's CLI unconditionally exits 1 with
 * "No files found to analyse." whenever the configured `paths` resolve to zero files
 * (see `AnalyseCommand::execute()` in vendor/phpstan/phpstan/phpstan.phar) — there is
 * no `phpstan.neon` parameter to make that condition non-fatal.
 *
 * This wrapper treats "zero .php files under src/" as a passing, no-op analysis:
 * there is nothing yet that could violate level 8. As soon as the first real class
 * lands under src/, this wrapper simply delegates to the real `phpstan analyse` and
 * forwards its exit code verbatim — no rule is ever weakened or skipped once there is
 * something to analyse.
 *
 * Written in plain PHP (not a shell one-liner) and invoked via composer's `@php`
 * prefix so it behaves identically on Windows dev machines and the Linux CI runners.
 */

$root = __DIR__;
$srcDir = $root . '/src';

$srcHasPhpFiles = false;

if (is_dir($srcDir)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcDir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
            $srcHasPhpFiles = true;
            break;
        }
    }
}

if (!$srcHasPhpFiles) {
    fwrite(STDOUT, "phpstan: no .php files under src/ yet (greenfield skeleton) - skipping analysis.\n");
    exit(0);
}

$phpstanBin = $root . '/vendor/bin/phpstan';
$cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($phpstanBin) . ' analyse';

passthru($cmd, $exitCode);
exit($exitCode);
