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

        // The fixture has 4 data rows: IID 700 (CH), IID 9000 (CH), a
        // Concatenation='Y' merger stub (IID 4835, CH) that must be
        // skipped, and IID 8810 (LI) that must be excluded by the CH-only
        // country filter -- only the 2 CH rows survive.
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

    /**
     * Regression test for the latent country-filtering bug: the fixture's
     * `Country=LI` row (IID 8810, Liechtensteinische Landesbank AG) must
     * NEVER surface as a CH row -- the shared SIX Bank Master V3 file lists
     * both Swiss and Liechtenstein banks, and only this importer's
     * `Country === 'CH'` filter (applied by
     * {@see \Daycry\Iban\Import\Importers\Concerns\ParsesSixBankMaster})
     * keeps it out.
     *
     * @see \Daycry\Iban\Import\Importers\LiechtensteinImporter
     */
    public function testRowsExcludesLiechtensteinRowsFromTheSharedSixFixture(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        $bankCodes = array_column($rows, 'bank_code');

        self::assertNotContains('08810', $bankCodes);

        foreach ($rows as $row) {
            self::assertNotSame('Liechtensteinische Landesbank AG', $row['name']);
        }
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/six.csv'), false);

        self::assertSame([], $rows);
    }
}
