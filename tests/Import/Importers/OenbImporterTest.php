<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\OenbImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see OenbImporter} in isolation (plain PHPUnit, framework-free)
 * against the hand-crafted fixture `tests/Fixtures/import/oenb_sample.csv`
 * (a representative sample of OeNB's real, live Bankstellenverzeichnis CSV,
 * confirmed via WebFetch when this importer was authored).
 *
 * @see \Daycry\Iban\Import\Importers\OenbImporter
 */
final class OenbImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/oenb_sample.csv';

    private OenbImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new OenbImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('AT', $this->importer->countryCode());
        self::assertSame('oenb', $this->importer->sourceId());
        self::assertSame('Oesterreichische Nationalbank', $this->importer->sourceName());
        self::assertSame('CC-BY-4.0 (OeNB)', $this->importer->license());
        self::assertStringStartsWith('https://www.oenb.at/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsYieldsOnlyHeadOfficeRowsFromTheFixture(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has 3 data rows, read in this order: a head office
        // ("Hauptanstalt", BLZ 12000), then a branch ("Zweigstelle") of that
        // SAME BLZ -- which must be skipped -- then a second head office
        // (BLZ 20111). So exactly 2 rows are yielded, in this order.
        self::assertCount(2, $rows);

        $bankAustria = $rows[0];
        self::assertSame('12000', $bankAustria['bank_code']);
        self::assertSame('Bank Austria', $bankAustria['name']);
        self::assertSame('Wien', $bankAustria['city']);
        self::assertSame('Rothschildplatz 1, 1020 Wien', $bankAustria['address']);
        self::assertNull($bankAustria['branch_code']);
        self::assertArrayNotHasKey('bic', $bankAustria);

        $ersteBank = $rows[1];
        self::assertSame('20111', $ersteBank['bank_code']);
        self::assertSame('Erste Bank der oesterreichischen Sparkassen AG', $ersteBank['name']);
        self::assertSame('Wien', $ersteBank['city']);
        self::assertSame('Am Belvedere 1, 1100 Wien', $ersteBank['address']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/oenb.csv'), false);

        self::assertSame([], $rows);
    }
}
