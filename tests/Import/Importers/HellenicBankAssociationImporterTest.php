<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\HellenicBankAssociationImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see HellenicBankAssociationImporter} in isolation (plain
 * PHPUnit, framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/hba_sample.csv` (a representative sample of the
 * Hellenic Bank Association's real, live HEBIC institutions list, encoded
 * as genuine Windows-1253 bytes, confirmed live 2026-07-11).
 *
 * @see \Daycry\Iban\Import\Importers\HellenicBankAssociationImporter
 */
final class HellenicBankAssociationImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/hba_sample.csv';

    private HellenicBankAssociationImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new HellenicBankAssociationImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('GR', $this->importer->countryCode());
        self::assertSame('hba', $this->importer->sourceId());
        self::assertSame('Hellenic Bank Association (HEBIC)', $this->importer->sourceName());
        self::assertSame('Hellenic Bank Association (HEBIC)', $this->importer->license());
        self::assertStringStartsWith('https://www.hba.gr/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testFixtureIsGenuineWindows1253Bytes(): void
    {
        // Sanity check on the fixture itself: it must NOT already be valid
        // UTF-8, so the parsing test below genuinely exercises the
        // Windows-1253 -> UTF-8 fallback conversion rather than a no-op.
        $raw = file_get_contents(self::FIXTURE);
        self::assertNotFalse($raw);
        self::assertFalse(mb_check_encoding($raw, 'UTF-8'));
    }

    public function testRowsParsesTheFixtureStripsQuotesAndSkipsThePreamble(): void
    {
        // The fixture has a title row + a header row (both must be
        // skipped) followed by 5 real data rows.
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        self::assertCount(5, $rows);

        $nbg = $rows[0];
        self::assertSame('011', $nbg['bank_code']); // quotes stripped, leading zero kept
        self::assertSame('NATIONAL BANK OF GREECE S.A.', $nbg['name']);
        self::assertNull($nbg['branch_code']);

        self::assertSame('014', $rows[1]['bank_code']);
        self::assertSame('ALPHA BANK S.A.', $rows[1]['name']);

        self::assertSame('017', $rows[2]['bank_code']);
        self::assertSame('PIRAEUS BANK S.A.', $rows[2]['name']);

        self::assertSame('026', $rows[3]['bank_code']);
        self::assertSame('EUROBANK S.A.', $rows[3]['name']);

        self::assertSame('073', $rows[4]['bank_code']);
        self::assertSame('BANK OF CYPRUS PUBLIC COMPANY LTD*', $rows[4]['name']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/hba.csv'), false);

        self::assertSame([], $rows);
    }
}
