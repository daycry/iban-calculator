<?php

declare(strict_types=1);

namespace Tests\Models;

use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Iban\Models\BankModel;
use ReflectionProperty;

/**
 * Exercises `BankModel`'s constructor overrides for `$table` / `$DBGroup`
 * (Fix 1 of the final v1.0 review): this is how
 * `Config\Services::iban()` wires `Config\Iban::$table` /
 * `Config\Iban::$dbGroup` into the DB layer instead of silently ignoring
 * them.
 *
 * These are unit-level assertions on the constructed instance's protected
 * properties (via reflection) -- no query is ever run, so no real DB
 * connection is opened (`Database::connect()` builds the connection object
 * lazily; see `Config\Database`/`BaseConnection`). For a functional,
 * query-level proof that a custom `$table` is actually queried instead of
 * `banks`, see `tests/Database/DatabaseProviderTest.php`.
 *
 * @see \Daycry\Iban\Models\BankModel
 * @see \Daycry\Iban\Config\Services::iban()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §7
 */
final class BankModelTest extends CIUnitTestCase
{
    public function testDefaultConstructionUsesTheBanksTableAndLeavesDbGroupUnset(): void
    {
        $model = new BankModel();

        self::assertSame('banks', self::tableOf($model));
        self::assertNull(self::dbGroupOf($model));
    }

    public function testCustomTableOverridesTheDefault(): void
    {
        $model = new BankModel('custom_banks');

        self::assertSame('custom_banks', self::tableOf($model));
        self::assertNull(self::dbGroupOf($model));
    }

    public function testCustomDbGroupOverridesTheDefault(): void
    {
        // 'tests' is a real, registered connection group (SQLite3
        // `:memory:`; see phpunit.xml's bootstrap note). Reusing it here,
        // rather than a made-up name, keeps this test from tripping
        // `Database::connect()`'s "not a valid database connection group"
        // guard while still proving the override is honored.
        $model = new BankModel(null, 'tests');

        self::assertSame('banks', self::tableOf($model));
        self::assertSame('tests', self::dbGroupOf($model));
    }

    public function testBothTableAndDbGroupCanBeOverriddenTogether(): void
    {
        $model = new BankModel('custom_banks', 'tests');

        self::assertSame('custom_banks', self::tableOf($model));
        self::assertSame('tests', self::dbGroupOf($model));
    }

    public function testExplicitNullArgumentsPreserveBothDefaults(): void
    {
        $model = new BankModel(null, null);

        self::assertSame('banks', self::tableOf($model));
        self::assertNull(self::dbGroupOf($model));
    }

    private static function tableOf(BankModel $model): string
    {
        $property = new ReflectionProperty(BankModel::class, 'table');
        $property->setAccessible(true);

        /** @var string $value */
        $value = $property->getValue($model);

        return $value;
    }

    private static function dbGroupOf(BankModel $model): ?string
    {
        $property = new ReflectionProperty(BankModel::class, 'DBGroup');
        $property->setAccessible(true);

        /** @var string|null $value */
        $value = $property->getValue($model);

        return $value;
    }
}
