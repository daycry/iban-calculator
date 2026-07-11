<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\BundesbankImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see BundesbankImporter} in isolation (plain PHPUnit,
 * framework-free) against the hand-crafted fixed-width fixture
 * `tests/Fixtures/import/bundesbank_sample.txt`, built to the exact
 * 168-char record layout documented in the Deutsche Bundesbank's official
 * "Merkblatt Bankleitzahlendatei" (Stand: 20. Dezember 2022), Anhang 1.
 *
 * @see \Daycry\Iban\Import\Importers\BundesbankImporter
 */
final class BundesbankImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/bundesbank_sample.txt';

    private BundesbankImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new BundesbankImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('DE', $this->importer->countryCode());
        self::assertSame('bundesbank', $this->importer->sourceId());
        self::assertSame('Deutsche Bundesbank', $this->importer->sourceName());
        self::assertSame('Deutsche Bundesbank', $this->importer->license());
        self::assertStringStartsWith('https://www.bundesbank.de/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsYieldsOnlyMerkmalOneRecordsFromTheFixture(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has 3 fixed-width lines, read in this order: a
        // Merkmal '1' (principal) record for BLZ 37040044, then a Merkmal
        // '2' (subordinate) record for that SAME BLZ -- which must be
        // skipped -- then a second Merkmal '1' record for BLZ 50010517. So
        // exactly 2 rows are yielded, in this order.
        self::assertCount(2, $rows);

        $commerzbank = $rows[0];
        self::assertSame('37040044', $commerzbank['bank_code']);
        self::assertSame('Commerzbank', $commerzbank['name']);
        self::assertSame('Commerzbank Essen', $commerzbank['short_name']);
        self::assertSame('Essen', $commerzbank['city']);
        self::assertSame('COBADEFFXXX', $commerzbank['bic']);
        self::assertNull($commerzbank['branch_code']);

        $ingDiba = $rows[1];
        self::assertSame('50010517', $ingDiba['bank_code']);
        self::assertSame('ING-DiBa', $ingDiba['name']);
        self::assertSame('ING-DiBa Frankfurt', $ingDiba['short_name']);
        self::assertSame('Frankfurt am Main', $ingDiba['city']);
        self::assertSame('INGDDEFFXXX', $ingDiba['bic']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/blz.txt'), false);

        self::assertSame([], $rows);
    }
}
