<?php

declare(strict_types=1);

namespace Tests\Bin;

use function generate_countries_php;

use PHPUnit\Framework\TestCase;

/**
 * Drift test for the registry generator (T-28/T-29/T-30).
 *
 * `bin/generate-registry.php` is `require_once`-d for its
 * `generate_countries_php()` function only — the file is guarded (see its
 * bottom) so that `require`-ing it does NOT invoke `main()`.
 *
 * @see docs/registry-authoring.md
 * @see .superpowers/sdd/task-28-30-brief.md
 */
final class GenerateRegistryTest extends TestCase
{
    private const string REPO_ROOT = __DIR__ . '/../..';

    public static function setUpBeforeClass(): void
    {
        require_once self::REPO_ROOT . '/bin/generate-registry.php';
    }

    /**
     * The committed `src/Registry/data/countries.php` must be EXACTLY the
     * generator's output for the committed `bin/data/registry-facts.csv` —
     * byte for byte. If this fails, either the CSV was edited without
     * regenerating (`php bin/generate-registry.php`), or `countries.php`
     * was hand-edited directly. Either way, the registry has drifted from
     * its fact source.
     */
    public function testGeneratedOutputMatchesCommittedCountriesPhpByteForByte(): void
    {
        $csvPath    = self::REPO_ROOT . '/bin/data/registry-facts.csv';
        $targetPath = self::REPO_ROOT . '/src/Registry/data/countries.php';

        $generated = generate_countries_php($csvPath);
        $committed = file_get_contents($targetPath);

        self::assertNotFalse($committed, sprintf('Could not read "%s".', $targetPath));
        self::assertSame(
            $generated,
            $committed,
            'src/Registry/data/countries.php has drifted from bin/data/registry-facts.csv. '
            . 'Regenerate with `php bin/generate-registry.php` and commit the result.',
        );
    }

    /**
     * Sanity check on the fact source itself: exactly one data row per
     * country currently in the registry (78, per Registry::VERSION /
     * T-16-18). Guards against an accidental truncated or duplicated CSV
     * slipping past the byte-for-byte check above (which would only fail
     * once countries.php is regenerated to match it).
     */
    public function testRegistryFactsCsvHas78DataRows(): void
    {
        $csvPath = self::REPO_ROOT . '/bin/data/registry-facts.csv';
        $lines   = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        self::assertNotFalse($lines, sprintf('Could not read "%s".', $csvPath));

        // Subtract 1 for the header row.
        self::assertCount(79, $lines, 'Expected a header row plus 78 country data rows.');
        self::assertSame(
            'code,iban_length,bban_structure,bank_offset,bank_length,branch_offset,branch_length,'
            . 'account_offset,account_length,national_check_offset,national_check_length,sepa,example',
            $lines[0],
        );
    }
}
