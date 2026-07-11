<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\NationalBankOfSlovakiaImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see NationalBankOfSlovakiaImporter} in isolation (plain
 * PHPUnit, framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/nbs_sample.csv` (a representative sample of the
 * National Bank of Slovakia's real, live "Prevodník identifikačných kódov
 * SR" CSV, encoded as genuine Windows-1250 bytes, confirmed live
 * 2026-07-11), plus one fixture-only row for bank code `1200` -- the SK
 * registry's own canonical example bank code (see
 * `src/Registry/data/countries.php`'s `'SK' => 'example' =>
 * 'SK3112000000198742637541'`) that is NOT present in the live snapshot,
 * added here so `ImportRunnerImportersTest`'s resolve() proof has a
 * matching seeded row.
 *
 * @see \Daycry\Iban\Import\Importers\NationalBankOfSlovakiaImporter
 */
final class NationalBankOfSlovakiaImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/nbs_sample.csv';

    private NationalBankOfSlovakiaImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new NationalBankOfSlovakiaImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('SK', $this->importer->countryCode());
        self::assertSame('nbs', $this->importer->sourceId());
        self::assertSame('National Bank of Slovakia', $this->importer->sourceName());
        self::assertSame('National Bank of Slovakia', $this->importer->license());
        self::assertStringStartsWith('https://www.nbs.sk/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testFixtureIsGenuineWindows1250Bytes(): void
    {
        // Sanity check on the fixture itself: it must NOT already be valid
        // UTF-8, so the parsing test below genuinely exercises the
        // Windows-1250 -> UTF-8 fallback conversion rather than a no-op.
        $raw = file_get_contents(self::FIXTURE);
        self::assertNotFalse($raw);
        self::assertFalse(mb_check_encoding($raw, 'UTF-8'));
    }

    public function testRowsZeroPadsTheBankCodeAndSkipsTheTitleAndHeaderRows(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has a title row + a header row (both must be
        // skipped) plus a `#`-prefixed explanatory comment row, followed by
        // 4 real data rows.
        self::assertCount(4, $rows);

        $vub = $rows[0];
        self::assertSame('0200', $vub['bank_code']); // '200' left-padded to 4 digits
        self::assertSame('Všeobecná úverová banka, a.s.', $vub['name']); // UTF-8 accented char round trip
        self::assertSame('SUBASKBX', $vub['bic']);
        self::assertNull($vub['branch_code']);

        $slsp = $rows[1];
        self::assertSame('0900', $slsp['bank_code']); // '900' left-padded to 4 digits
        self::assertSame('Slovenská sporiteľňa, a.s.', $slsp['name']);
        self::assertSame('GIBASKBX', $slsp['bic']);

        $tatra = $rows[2];
        self::assertSame('1100', $tatra['bank_code']); // already 4 digits
        self::assertSame('Tatra banka, a.s.', $tatra['name']);
        self::assertSame('TATRSKBX', $tatra['bic']);

        // The fixture-only row added for the registry's IBAN example bank code.
        $example = $rows[3];
        self::assertSame('1200', $example['bank_code']);
        self::assertSame('Príklad Banka, a.s. (fixture — SK registry example bank code)', $example['name']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/nbs.csv'), false);

        self::assertSame([], $rows);
    }
}
