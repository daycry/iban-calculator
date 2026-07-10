<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Architecture rule: `src/Core/` and `src/Contracts/` must stay framework-free.
 *
 * The Core is meant to work with zero dependencies (validation, parsing,
 * formatting, MOD-97) and must not know about CodeIgniter 4. Only the thin
 * CI4 adapter layer (Config/, Commands/, Models/, Database/, Providers/) is
 * allowed to depend on `codeigniter4/*`.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §3
 */
final class CoreIsFrameworkFreeTest extends TestCase
{
    /** @var string[] */
    private const GUARDED_DIRECTORIES = [
        'Core',
        'Contracts',
    ];

    /** @var string[] */
    private const FORBIDDEN_NEEDLES = [
        'CodeIgniter\\',
        'codeigniter4',
    ];

    public function testCoreAndContractsDoNotReferenceCodeIgniter(): void
    {
        $violations = [];

        foreach (self::GUARDED_DIRECTORIES as $directory) {
            $path = dirname(__DIR__, 2) . '/src/' . $directory;

            foreach ($this->phpFilesIn($path) as $file) {
                $contents = file_get_contents($file->getPathname());

                if ($contents === false) {
                    continue;
                }

                foreach (self::FORBIDDEN_NEEDLES as $needle) {
                    if (str_contains($contents, $needle)) {
                        $violations[] = sprintf(
                            '%s references "%s"',
                            $file->getPathname(),
                            $needle
                        );
                    }
                }
            }
        }

        self::assertSame(
            [],
            $violations,
            "src/Core and src/Contracts must be framework-free (no CodeIgniter\\ / codeigniter4 references):\n"
                . implode("\n", $violations)
        );
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function phpFilesIn(string $path): iterable
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}
