<?php

declare(strict_types=1);

namespace Tests\Registry;

use Daycry\Iban\Registry\PhpIsoCountryLoader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Data-integrity safety net for the ISO 3166-1 country registry
 * (`src/Registry/data/iso_countries.php`).
 *
 * Mirrors {@see CountriesDataIntegrityTest} for the IBAN structural registry:
 * it asserts the shape and internal consistency of every row (well-formed
 * codes, uniqueness, sort order, exact count) so a mis-authored entry — a
 * lowercase key, a duplicate alpha-3, a two-digit numeric — fails loudly.
 *
 * @see docs/licensing.md
 */
final class IsoCountriesDataIntegrityTest extends TestCase
{
    /**
     * The number of officially assigned ISO 3166-1 alpha-2 codes as of the
     * registry's authoring date. User-assigned codes (e.g. `XK`) are
     * deliberately excluded.
     */
    private const OFFICIALLY_ASSIGNED_COUNT = 249;

    /**
     * @return array<string, array{name: string, alpha3: string, numeric: string}>
     */
    private static function data(): array
    {
        return (new PhpIsoCountryLoader())->load();
    }

    public function testEntryCountEqualsOfficiallyAssignedCount(): void
    {
        self::assertCount(self::OFFICIALLY_ASSIGNED_COUNT, self::data());
    }

    public function testEveryAlpha2KeyIsTwoUppercaseLettersAndUnique(): void
    {
        $keys = array_keys(self::data());

        foreach ($keys as $key) {
            self::assertMatchesRegularExpression('/^[A-Z]{2}$/', $key, sprintf('Alpha-2 key "%s" is malformed.', $key));
        }

        self::assertSame($keys, array_values(array_unique($keys)), 'Alpha-2 keys must be unique.');
    }

    public function testEveryAlpha3IsThreeUppercaseLettersAndUnique(): void
    {
        $alpha3 = [];

        foreach (self::data() as $key => $row) {
            self::assertMatchesRegularExpression(
                '/^[A-Z]{3}$/',
                $row['alpha3'],
                sprintf('alpha3 "%s" for "%s" is malformed.', $row['alpha3'], $key),
            );
            $alpha3[] = $row['alpha3'];
        }

        self::assertSame($alpha3, array_values(array_unique($alpha3)), 'alpha3 codes must be unique.');
    }

    public function testEveryNumericIsThreeDigitsAndUnique(): void
    {
        $numeric = [];

        foreach (self::data() as $key => $row) {
            self::assertMatchesRegularExpression(
                '/^[0-9]{3}$/',
                $row['numeric'],
                sprintf('numeric "%s" for "%s" is malformed.', $row['numeric'], $key),
            );
            $numeric[] = $row['numeric'];
        }

        self::assertSame($numeric, array_values(array_unique($numeric)), 'numeric codes must be unique.');
    }

    public function testEveryRowHasExactlyTheThreeExpectedKeys(): void
    {
        foreach (self::data() as $key => $row) {
            self::assertSame(
                ['name', 'alpha3', 'numeric'],
                array_keys($row),
                sprintf('Row "%s" must have exactly the keys name/alpha3/numeric.', $key),
            );
            self::assertNotSame('', $row['name'], sprintf('Row "%s" has an empty name.', $key));
        }
    }

    public function testDataIsSortedByAlpha2Key(): void
    {
        $keys   = array_keys(self::data());
        $sorted = $keys;
        sort($sorted, SORT_STRING);

        self::assertSame($sorted, $keys, 'iso_countries.php must be sorted by alpha-2 key.');
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function spotCheckProvider(): iterable
    {
        // alpha2, expected name (substring), alpha3, numeric
        yield 'Spain'          => ['ES', 'Spain', 'ESP', '724'];
        yield 'United States'  => ['US', 'United States', 'USA', '840'];
        yield 'Germany'        => ['DE', 'Germany', 'DEU', '276'];
        yield 'Japan'          => ['JP', 'Japan', 'JPN', '392'];
        yield 'United Kingdom' => ['GB', 'United Kingdom', 'GBR', '826'];
        yield 'New Zealand'    => ['NZ', 'New Zealand', 'NZL', '554'];
    }

    #[DataProvider('spotCheckProvider')]
    public function testKnownRowsAreCorrect(string $alpha2, string $nameSubstring, string $alpha3, string $numeric): void
    {
        $data = self::data();

        self::assertArrayHasKey($alpha2, $data);
        self::assertStringContainsString($nameSubstring, $data[$alpha2]['name']);
        self::assertSame($alpha3, $data[$alpha2]['alpha3']);
        self::assertSame($numeric, $data[$alpha2]['numeric']);
    }

    public function testKosovoIsDeliberatelyExcluded(): void
    {
        // XK is a user-assigned code (used in live BICs) but is NOT an
        // officially assigned ISO 3166-1 code, so it must not appear in this
        // registry; BIC validation layers it on separately.
        self::assertArrayNotHasKey('XK', self::data());
    }
}
