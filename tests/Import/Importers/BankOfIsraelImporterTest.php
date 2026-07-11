<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\BankOfIsraelImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see BankOfIsraelImporter} in isolation (plain PHPUnit,
 * framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/boi_sample.json` -- a representative,
 * structurally faithful slice of the Bank of Israel's real, live CKAN
 * `datastore_search` JSON response (confirmed live 2026-07-11 via WebFetch
 * and a direct `curl` against the resource), including the per-branch
 * rollup this importer must dedup down to one row per bank.
 *
 * @see \Daycry\Iban\Import\Importers\BankOfIsraelImporter
 */
final class BankOfIsraelImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/boi_sample.json';

    private BankOfIsraelImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new BankOfIsraelImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('IL', $this->importer->countryCode());
        self::assertSame('boi', $this->importer->sourceId());
        self::assertSame('Bank of Israel (data.gov.il)', $this->importer->sourceName());
        self::assertSame('Bank of Israel (data.gov.il, other-open)', $this->importer->license());
        self::assertStringStartsWith('https://data.gov.il/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsRollsUpBranchesToBankLevelDedupingByZeroPaddedBankCode(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has 7 branch records across 4 distinct Bank_Codes
        // ('1', '3' x2, '4' x2, '10' x2) -- only the FIRST branch row for
        // each Bank_Code is yielded, so exactly 4 bank-level rows result.
        self::assertCount(4, $rows);

        $isracard = $rows[0];
        self::assertSame('001', $isracard['bank_code']); // '1' zero-padded to 3 digits
        self::assertSame('ISRACARD LTD.', $isracard['name']); // trailing space trimmed
        self::assertNull($isracard['branch_code']);

        $eshIsrael = $rows[1];
        self::assertSame('003', $eshIsrael['bank_code']);
        self::assertSame('Bank Esh Israel Ltd', $eshIsrael['name']);

        $yahav = $rows[2];
        self::assertSame('004', $yahav['bank_code']);
        self::assertSame('Bank Yahav  for Government Employees Ltd', $yahav['name']);

        // The registry's own example bank code (see the IBAN example
        // IL620108000000099999999 -> bank code '010').
        $leumi = $rows[3];
        self::assertSame('010', $leumi['bank_code']); // '10' zero-padded to 3 digits
        self::assertSame('Bank Leumi Le-Israel B.M', $leumi['name']);
        self::assertNull($leumi['branch_code']);

        $bankCodes = array_column($rows, 'bank_code');
        self::assertSame(['001', '003', '004', '010'], $bankCodes);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/boi.json'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableForMalformedJson(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'iban_boi_malformed_');
        self::assertIsString($path);
        file_put_contents($path, '{ this is not valid JSON');

        try {
            $rows = iterator_to_array($this->importer->rows($path), false);

            self::assertSame([], $rows);
        } finally {
            unlink($path);
        }
    }

    public function testRowsReturnsEmptyIterableWhenTheResultEnvelopeIsMissing(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'iban_boi_no_result_');
        self::assertIsString($path);
        file_put_contents($path, json_encode(['help' => 'x', 'success' => true]));

        try {
            $rows = iterator_to_array($this->importer->rows($path), false);

            self::assertSame([], $rows);
        } finally {
            unlink($path);
        }
    }
}
