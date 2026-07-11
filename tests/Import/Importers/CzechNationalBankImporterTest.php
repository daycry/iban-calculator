<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\CzechNationalBankImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see CzechNationalBankImporter} in isolation (plain PHPUnit,
 * framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/cnb_sample.csv` (a representative sample of the
 * Czech National Bank's real, live "Kódy bank v ČR" CSV, WITH a genuine
 * leading UTF-8 BOM byte, confirmed live 2026-07-11).
 *
 * @see \Daycry\Iban\Import\Importers\CzechNationalBankImporter
 */
final class CzechNationalBankImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/cnb_sample.csv';

    private CzechNationalBankImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new CzechNationalBankImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('CZ', $this->importer->countryCode());
        self::assertSame('cnb', $this->importer->sourceId());
        self::assertSame('Czech National Bank', $this->importer->sourceName());
        self::assertSame('Czech National Bank (cite source, no changes)', $this->importer->license());
        self::assertStringStartsWith('https://www.cnb.cz/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testFixtureReallyStartsWithAUtf8Bom(): void
    {
        $raw = file_get_contents(self::FIXTURE);
        self::assertNotFalse($raw);
        self::assertStringStartsWith("\xEF\xBB\xBF", $raw);
    }

    public function testRowsParsesTheFixtureAndSkipsTheHeaderRow(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has 8 real data rows (the header row must be skipped).
        self::assertCount(8, $rows);

        $komercni = $rows[0];
        self::assertSame('0100', $komercni['bank_code']);
        self::assertSame('Komerční banka, a.s.', $komercni['name']); // UTF-8 accented char round trip
        self::assertSame('KOMBCZPP', $komercni['bic']);
        self::assertNull($komercni['branch_code']);

        $ceskaSporitelna = $rows[4];
        self::assertSame('0800', $ceskaSporitelna['bank_code']);
        self::assertSame('Česká spořitelna, a.s.', $ceskaSporitelna['name']);
        self::assertSame('GIBACZPX', $ceskaSporitelna['bic']);

        // A row with no published BIC must yield a null `bic`, not an empty string.
        $csobHypotecni = $rows[6];
        self::assertSame('2100', $csobHypotecni['bank_code']);
        self::assertSame('ČSOB Hypoteční banka, a.s.', $csobHypotecni['name']);
        self::assertNull($csobHypotecni['bic']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/cnb.csv'), false);

        self::assertSame([], $rows);
    }
}
