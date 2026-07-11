<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\CentralBankOfMaltaImporter;
use PHPUnit\Framework\TestCase;
use Tests\_support\XlsxFixtureFactory;

/**
 * Exercises {@see CentralBankOfMaltaImporter} in isolation (plain PHPUnit,
 * framework-free) against a hand-crafted `.xlsx` fixture generated on the
 * fly with {@see XlsxFixtureFactory} -- reproducing the live CBM "BIC sort
 * codes" list's confirmed layout: no preamble, header row 1 with the real
 * `BIC Code` / `Financial Institution Name` / `National ID (Sort Code)` /
 * `Branch` / `Remarks` columns (confirmed live 2026-07-11), including the
 * per-sort-code repetition this importer must dedup.
 *
 * @see \Daycry\Iban\Import\Importers\CentralBankOfMaltaImporter
 */
final class CentralBankOfMaltaImporterTest extends TestCase
{
    private CentralBankOfMaltaImporter $importer;

    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new CentralBankOfMaltaImporter();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->fixturePath !== null && is_file($this->fixturePath)) {
            unlink($this->fixturePath);
        }

        $this->fixturePath = null;
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('MT', $this->importer->countryCode());
        self::assertSame('cbm', $this->importer->sourceId());
        self::assertSame('Central Bank of Malta', $this->importer->sourceName());
        self::assertSame('Central Bank of Malta', $this->importer->license());
        self::assertStringStartsWith('https://www.centralbankmalta.org/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsDedupsByTheFourCharBankCodeKeepingTheFirstOccurrence(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['BIC Code', 'Financial Institution Name', 'National ID (Sort Code)', 'Branch', 'Remarks'],
            ['MALTMTMT', 'Central Bank of Malta', '01100', 'Valletta', ''],
            ['LBMAMTMT', 'Lombard Bank Malta plc.', '05000', 'Head Office', 'Used in all IBANs'],
            // Continuation/branch rows for Lombard Bank: BIC and name blank,
            // only the sort code/branch differ -- must be skipped entirely
            // (no bank_code can be derived from an empty BIC).
            ['', '', '05016', 'Valletta', ''],
            ['', '', '05027', 'Sliema', ''],
            // Same institution, a second sort code, DIFFERENT full BIC but
            // the SAME 4-char prefix ('PYMX') -- must be deduped away.
            ['PYMXMTMTXXX', 'Finance Incorporated Ltd.', '09014', 'Swatar', ''],
            ['PYMXMTMTMAL', 'Finance Incorporated Ltd.', '09025', 'Swatar', ''],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        // MALT, LBMA, PYMX -- 3 unique 4-char bank codes; the 2 blank
        // continuation rows and the PYMX duplicate are all excluded.
        self::assertCount(3, $rows);

        $centralBank = $rows[0];
        self::assertSame('MALT', $centralBank['bank_code']);
        self::assertNull($centralBank['branch_code']);
        self::assertSame('Central Bank of Malta', $centralBank['name']);
        self::assertSame('MALTMTMT', $centralBank['bic']);

        $lombard = $rows[1];
        self::assertSame('LBMA', $lombard['bank_code']);
        self::assertSame('Lombard Bank Malta plc.', $lombard['name']);
        self::assertSame('LBMAMTMT', $lombard['bic']);

        // The FIRST occurrence's name/bic win -- the deduped sibling
        // ('PYMXMTMTMAL') must not have overwritten this one.
        $finance = $rows[2];
        self::assertSame('PYMX', $finance['bank_code']);
        self::assertSame('Finance Incorporated Ltd.', $finance['name']);
        self::assertSame('PYMXMTMTXXX', $finance['bic']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/cbm.xlsx'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenTheHeaderCannotBeLocated(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['Unexpected', 'Layout'],
            ['MALTMTMT', 'Something'],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertSame([], $rows);
    }
}
