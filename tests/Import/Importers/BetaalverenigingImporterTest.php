<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\BetaalverenigingImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see BetaalverenigingImporter} in isolation (plain PHPUnit,
 * framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/betaalvereniging_sample.csv` (a CSV export of
 * Betaalvereniging Nederland's real "BIC-lijst NL", replicating its
 * 2-row preamble).
 *
 * @see \Daycry\Iban\Import\Importers\BetaalverenigingImporter
 */
final class BetaalverenigingImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/betaalvereniging_sample.csv';

    private BetaalverenigingImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new BetaalverenigingImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('NL', $this->importer->countryCode());
        self::assertSame('betaalvereniging', $this->importer->sourceId());
        self::assertSame('Betaalvereniging Nederland', $this->importer->sourceName());
        self::assertSame('Betaalvereniging Nederland (see terms)', $this->importer->license());
        self::assertStringStartsWith('https://www.betaalvereniging.nl/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsSkipsTheTitleAndHeaderPreambleRows(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has a title row + a header row (both must be
        // skipped) followed by 3 real data rows (ABNA/INGB/RABO).
        self::assertCount(3, $rows);

        $abnAmro = $rows[0];
        self::assertSame('ABNA', $abnAmro['bank_code']);
        self::assertSame('ABNANL2A', $abnAmro['bic']);
        self::assertSame('ABN AMRO BANK N.V.', $abnAmro['name']);
        self::assertNull($abnAmro['branch_code']);

        self::assertSame('INGB', $rows[1]['bank_code']);
        self::assertSame('INGBNL2A', $rows[1]['bic']);
        self::assertSame('ING', $rows[1]['name']);

        self::assertSame('RABO', $rows[2]['bank_code']);
        self::assertSame('RABONL2U', $rows[2]['bic']);
        self::assertSame('RABOBANK', $rows[2]['name']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/betaalvereniging.csv'), false);

        self::assertSame([], $rows);
    }
}
