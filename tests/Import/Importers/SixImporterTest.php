<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\SixImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see SixImporter} in isolation (plain PHPUnit, framework-free)
 * against the hand-crafted fixture `tests/Fixtures/import/six_sample.csv`
 * (a representative sample of SIX Interbank Clearing's real, live Bank
 * Master V3 CSV, confirmed live 2026-07-11).
 *
 * @see \Daycry\Iban\Import\Importers\SixImporter
 */
final class SixImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/six_sample.csv';

    private SixImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new SixImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('CH', $this->importer->countryCode());
        self::assertSame('six', $this->importer->sourceId());
        self::assertSame('SIX Interbank Clearing', $this->importer->sourceName());
        self::assertSame('SIX Interbank Clearing (free use)', $this->importer->license());
        self::assertStringStartsWith('https://api.six-group.com/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsZeroPadsTheIidAndSkipsTheConcatenationStubRow(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has 3 data rows: IID 700, IID 9000, and a
        // Concatenation='Y' merger stub (IID 4835) that must be skipped.
        self::assertCount(2, $rows);

        $zkb = $rows[0];
        self::assertSame('00700', $zkb['bank_code']); // '700' left-padded to 5 digits
        self::assertNull($zkb['branch_code']);
        self::assertSame('Zürcher Kantonalbank', $zkb['name']); // UTF-8 round trip
        self::assertSame('Zürich', $zkb['city']);
        self::assertSame('ZKBKCHZZ80A', $zkb['bic']);
        self::assertSame('Postfach', $zkb['address']);

        $postFinance = $rows[1];
        self::assertSame('09000', $postFinance['bank_code']); // '9000' left-padded to 5 digits
        self::assertSame('PostFinance AG', $postFinance['name']);
        self::assertSame('Bern', $postFinance['city']);
        self::assertSame('POFICHBEXXX', $postFinance['bic']);
        self::assertSame('Mingerstrasse 20', $postFinance['address']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/six.csv'), false);

        self::assertSame([], $rows);
    }
}
