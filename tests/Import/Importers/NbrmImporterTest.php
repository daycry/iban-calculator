<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\NbrmImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see NbrmImporter} in isolation (plain PHPUnit, framework-free)
 * against a reduced fixture reproducing an operator-exported CSV of the NBRM
 * "Листа на доделени водечки броеви на банките" (`.xls`/`.docx` -> CSV): a
 * title/preamble line, a Cyrillic header row, then `Р.бр | SWIFT BIC | Назив
 * на банка | Водечки број` columns. The 3-digit "Водечки број" is the
 * `bank_code`; columns are located by header NAME, so their order in the
 * export doesn't matter.
 *
 * @see \Daycry\Iban\Import\Importers\NbrmImporter
 */
final class NbrmImporterTest extends TestCase
{
    private const CLEAN_FIXTURE = __DIR__ . '/../../Fixtures/import/nbrm_sample.csv';

    private NbrmImporter $importer;

    private ?string $tempFixture = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new NbrmImporter();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->tempFixture !== null && is_file($this->tempFixture)) {
            unlink($this->tempFixture);
        }

        $this->tempFixture = null;
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('MK', $this->importer->countryCode());
        self::assertSame('nbrm', $this->importer->sourceId());
        self::assertStringContainsString('NBRM', $this->importer->sourceName());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://www.nbrm.mk/', $this->importer->sourceUrl());
    }

    public function testRowsMapThreeDigitLeadingNumberToNameAndBicLocatingColumnsByHeader(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::CLEAN_FIXTURE), false);

        // 4 data rows; the title line and header row are skipped, and the
        // "Р.бр" row-number column is NOT mistaken for the bank code.
        self::assertCount(4, $rows);

        self::assertSame('200', $rows[0]['bank_code']);
        self::assertSame('Стопанска банка АД Скопје', $rows[0]['name']);
        self::assertSame('STOBMK2X', $rows[0]['bic']);
        self::assertNull($rows[0]['branch_code']);

        self::assertSame('210', $rows[1]['bank_code']);
        self::assertSame('НЛБ Банка АД Скопје', $rows[1]['name']);
        self::assertSame('TUTNMK22', $rows[1]['bic']);

        self::assertSame('300', $rows[2]['bank_code']);
        self::assertSame('Комерцијална банка АД Скопје', $rows[2]['name']);
        self::assertSame('KOBSMK2X', $rows[2]['bic']);

        // A row with no BIC yields null, not an empty string.
        self::assertSame('999', $rows[3]['bank_code']);
        self::assertNull($rows[3]['bic']);

        foreach ($rows as $row) {
            self::assertIsString($row['bank_code']);
            self::assertMatchesRegularExpression('/^\d{3}$/', $row['bank_code']);
        }
    }

    public function testRowsDecodeWindows1251CyrillicExportToUtf8(): void
    {
        // The authoritative source is a legacy .xls (BIFF, cp1251); an
        // operator export that kept that codepage instead of UTF-8 must still
        // decode its Cyrillic names correctly.
        $utf8 = (string) file_get_contents(self::CLEAN_FIXTURE);
        $cp   = (string) iconv('UTF-8', 'Windows-1251', $utf8);

        self::assertFalse(mb_check_encoding($cp, 'UTF-8'));

        $this->tempFixture = (string) tempnam(sys_get_temp_dir(), 'iban_mk_');
        file_put_contents($this->tempFixture, $cp);

        $rows = iterator_to_array($this->importer->rows($this->tempFixture), false);

        self::assertCount(4, $rows);
        self::assertSame('Стопанска банка АД Скопје', $rows[0]['name']);
        self::assertSame('300', $rows[2]['bank_code']);
        self::assertSame('Комерцијална банка АД Скопје', $rows[2]['name']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/nbrm.csv'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenNoHeaderCanBeLocated(): void
    {
        $this->tempFixture = (string) tempnam(sys_get_temp_dir(), 'iban_mk_');
        file_put_contents($this->tempFixture, "col1,col2,col3\na,b,c\n");

        $rows = iterator_to_array($this->importer->rows($this->tempFixture), false);

        self::assertSame([], $rows);
    }
}
