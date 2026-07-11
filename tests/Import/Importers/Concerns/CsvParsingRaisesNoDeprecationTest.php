<?php

declare(strict_types=1);

namespace Tests\Import\Importers\Concerns;

use Daycry\Iban\Import\Importers\OenbImporter;
use Daycry\Iban\Import\Importers\SixImporter;
use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the PHP 8.4+ `fgetcsv()` `$escape` deprecation.
 *
 * On PHP 8.4+, calling `fgetcsv()` without the `$escape` argument raises
 * `E_DEPRECATED` ("the $escape parameter must be provided as its default value
 * will change"). In a CI4 app with debug on, that deprecation makes the
 * framework render a backtrace that `var_export()`s the whole raw CSV, which
 * can exhaust memory on a real (multi-MB) source file. Both CSV-parsing traits
 * ({@see \Daycry\Iban\Import\Importers\Concerns\ReadsCsvSource} and
 * {@see \Daycry\Iban\Import\Importers\Concerns\ParsesSixBankMaster}) must call
 * `fgetcsv()` with the enclosure + empty escape so no deprecation is emitted.
 */
final class CsvParsingRaisesNoDeprecationTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../../Fixtures/import';

    /** @var list<string> */
    private array $deprecations = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->deprecations = [];

        set_error_handler(
            function (int $errno, string $errstr): bool {
                $this->deprecations[] = $errstr;

                return true;
            },
            E_DEPRECATED,
        );
    }

    protected function tearDown(): void
    {
        restore_error_handler();

        parent::tearDown();
    }

    public function testReadsCsvSourceEmitsNoFgetcsvDeprecation(): void
    {
        // OenbImporter parses via the ReadsCsvSource trait.
        iterator_to_array((new OenbImporter())->rows(self::FIXTURES . '/oenb_sample.csv'), false);

        self::assertSame([], $this->fgetcsvDeprecations());
    }

    public function testParsesSixBankMasterEmitsNoFgetcsvDeprecation(): void
    {
        // SixImporter parses via the ParsesSixBankMaster trait.
        iterator_to_array((new SixImporter())->rows(self::FIXTURES . '/six_sample.csv'), false);

        self::assertSame([], $this->fgetcsvDeprecations());
    }

    /**
     * @return list<string>
     */
    private function fgetcsvDeprecations(): array
    {
        return array_values(array_filter(
            $this->deprecations,
            static fn (string $message): bool => str_contains($message, 'fgetcsv'),
        ));
    }
}
