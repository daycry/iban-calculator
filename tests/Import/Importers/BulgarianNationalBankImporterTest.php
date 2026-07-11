<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\BulgarianNationalBankImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see BulgarianNationalBankImporter} in isolation (plain
 * PHPUnit, framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/bnb_sample.xml` -- a representative, structurally
 * faithful sample of the Bulgarian National Bank's real, live
 * "БАЕ кодове и BIC на банките" SpreadsheetML XML export (`BAE_BIC.xls`,
 * confirmed live 2026-07-11 to actually be XML, not a binary spreadsheet).
 *
 * @see \Daycry\Iban\Import\Importers\BulgarianNationalBankImporter
 */
final class BulgarianNationalBankImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/bnb_sample.xml';

    private BulgarianNationalBankImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new BulgarianNationalBankImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('BG', $this->importer->countryCode());
        self::assertSame('bnb', $this->importer->sourceId());
        self::assertSame('Bulgarian National Bank', $this->importer->sourceName());
        self::assertSame('Bulgarian National Bank', $this->importer->license());
        self::assertStringStartsWith('https://www.bnb.bg/', $this->importer->sourceUrl());
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

    public function testRowsParsesTheFixtureSkippingPreambleAndDedupingByFourLetterPrefix(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has 8 real data rows, but 3 share a 4-letter BAE-code
        // prefix with an earlier row (a regional/sibling code for the same
        // PSP), so only 5 unique bank_code rows are yielded.
        self::assertCount(5, $rows);

        $aikart = $rows[0];
        self::assertSame('INTF', $aikart['bank_code']);
        self::assertSame('Айкарт АД', $aikart['name']); // UTF-8 Cyrillic round trip
        self::assertSame('INTFBGSF', $aikart['bic']);
        self::assertNull($aikart['branch_code']);

        $allianz = $rows[1];
        self::assertSame('BUIN', $allianz['bank_code']);
        self::assertSame('Алианц Банк България АД', $allianz['name']);
        self::assertSame('BUINBGSF', $allianz['bic']);

        // The BNB's own example bank code (see the IBAN example
        // BG80BNBG96611020345678) -- kept as the FIRST BNBG row; its sibling
        // "БНБ СЕБРА плащания" (also prefixed BNBG) must have been deduped.
        $bnb = $rows[2];
        self::assertSame('BNBG', $bnb['bank_code']);
        self::assertSame('Българска народна банка', $bnb['name']);
        self::assertSame('BNBGBGSF', $bnb['bic']);

        $eurobank = $rows[3];
        self::assertSame('BPBI', $eurobank['bank_code']);
        self::assertSame('Юробанк България АД', $eurobank['name']);
        self::assertSame('BPBIBGSF', $eurobank['bic']);

        $iute = $rows[4];
        self::assertSame('IUTE', $iute['bank_code']);
        self::assertSame('ЮтеПей ЕООД', $iute['name']);
        self::assertSame('IUTEBGSF', $iute['bic']);

        // The deduped regional/sibling rows must not have produced their own entries.
        $bankCodes = array_column($rows, 'bank_code');
        self::assertSame(['INTF', 'BUIN', 'BNBG', 'BPBI', 'IUTE'], $bankCodes);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/bnb.xml'), false);

        self::assertSame([], $rows);
    }
}
