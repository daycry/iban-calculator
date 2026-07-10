<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Architecture rule: the framework-free core must stay free of CodeIgniter 4.
 *
 * The Core is meant to work with zero dependencies (validation, parsing,
 * formatting, MOD-97) and must not know about CodeIgniter 4. `Contracts`,
 * `DTO`, `Enums` and `Exceptions` are transitively depended upon by `Core`
 * and `Contracts`, so they must remain framework-free too in order to
 * preserve the standalone guarantee. Only the thin CI4 adapter layer
 * (Config/, Commands/, Models/, Database/, Helpers/, Providers/) is allowed
 * to depend on `codeigniter4/*`.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §3
 */
final class CoreIsFrameworkFreeTest extends TestCase
{
    /** @var string[] */
    private const GUARDED_DIRECTORIES = [
        'Core',
        'Contracts',
        'DTO',
        'Enums',
        'Exceptions',
        'Registry',
    ];

    /** @var string[] */
    private const FORBIDDEN_NEEDLES = [
        'CodeIgniter\\',
        'codeigniter4',
    ];

    public function testGuardedDirectoriesDoNotReferenceCodeIgniter(): void
    {
        $violations = [];

        foreach (self::GUARDED_DIRECTORIES as $directory) {
            $path = dirname(__DIR__, 2) . '/src/' . $directory;

            foreach ($this->phpFilesIn($path) as $file) {
                $contents = file_get_contents($file->getPathname());

                if ($contents === false) {
                    continue;
                }

                if (self::containsFrameworkReference($contents)) {
                    $violations[] = sprintf(
                        '%s references CodeIgniter',
                        $file->getPathname()
                    );
                }
            }
        }

        self::assertSame(
            [],
            $violations,
            'The framework-free core (' . implode(', ', self::GUARDED_DIRECTORIES)
                . ") must not reference CodeIgniter\\ / codeigniter4:\n"
                . implode("\n", $violations)
        );
    }

    /**
     * Negative self-test: proves {@see containsFrameworkReference()} actually
     * detects a violation instead of silently passing (e.g. an inverted or
     * broken predicate would still make the positive test above pass green).
     */
    public function testDetectorFlagsSourceContainingCodeIgniterReference(): void
    {
        $dirty = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Fixtures;

            use CodeIgniter\Config\BaseConfig;

            final class TaintedConfig extends BaseConfig
            {
            }
            PHP;

        self::assertTrue(
            self::containsFrameworkReference($dirty),
            'Expected the detector to flag source referencing CodeIgniter\\Config\\BaseConfig'
        );
    }

    /**
     * Negative self-test companion: proves clean source is NOT flagged, so the
     * detector isn't a trivial "always true" stub either.
     */
    public function testDetectorDoesNotFlagCleanSource(): void
    {
        $clean = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Fixtures;

            final class PlainValueObject
            {
                public function __construct(private readonly string $value)
                {
                }
            }
            PHP;

        self::assertFalse(
            self::containsFrameworkReference($clean),
            'Did not expect the detector to flag framework-free source'
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

    private static function containsFrameworkReference(string $phpSource): bool
    {
        foreach (self::FORBIDDEN_NEEDLES as $needle) {
            if (str_contains($phpSource, $needle)) {
                return true;
            }
        }

        return false;
    }
}
