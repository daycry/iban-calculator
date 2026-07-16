<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\BancoDePortugalImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see BancoDePortugalImporter} in isolation (plain PHPUnit,
 * framework-free) against a reduced fixture reproducing the operator-supplied,
 * `pdftotext -layout`-extracted text of Banco de Portugal's SICOI "BIC
 * associated with IBANs of PSPs participating in SICOI" document: a preamble
 * line, a header row, then `IBAN Bank Identifier` (4 digits) / PSP name / BIC
 * space-aligned columns.
 *
 * Two cleanup behaviours are pinned: (1) the code column keeps its leading
 * zeros as a STRING, and (2) accented PSP names are recovered to valid UTF-8
 * even when the operator's `pdftotext` emitted Windows-1252/Latin-1 bytes
 * (the "mojibake" case), proven by generating that byte variant on the fly.
 *
 * @see \Daycry\Iban\Import\Importers\BancoDePortugalImporter
 */
final class BancoDePortugalImporterTest extends TestCase
{
    private const CLEAN_FIXTURE = __DIR__ . '/../../Fixtures/import/bportugal_sample.txt';

    private BancoDePortugalImporter $importer;

    private ?string $tempFixture = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new BancoDePortugalImporter();
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
        self::assertSame('PT', $this->importer->countryCode());
        self::assertSame('bportugal', $this->importer->sourceId());
        self::assertStringContainsString('Banco de Portugal', $this->importer->sourceName());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://www.bportugal.pt/', $this->importer->sourceUrl());
    }

    public function testRowsMapFourDigitCodesToNameAndBicSkippingPreambleAndHeader(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::CLEAN_FIXTURE), false);

        // 4 data rows; the preamble line and the header row are skipped
        // (neither starts with a 4-digit code).
        self::assertCount(4, $rows);

        self::assertSame('0033', $rows[0]['bank_code']);
        self::assertSame('BNP Paribas', $rows[0]['name']);
        self::assertSame('BNPAPTPL', $rows[0]['bic']);
        self::assertNull($rows[0]['branch_code']);

        // Accented name survives as valid UTF-8 (clean, -enc UTF-8 recipe).
        self::assertSame('0034', $rows[1]['bank_code']);
        self::assertSame('Caixa Geral de Depósitos, S.A.', $rows[1]['name']);
        self::assertSame('CGDIPTPL', $rows[1]['bic']);

        self::assertSame('0035', $rows[2]['bank_code']);
        self::assertSame('Caixa Económica Montepio Geral', $rows[2]['name']);
        self::assertSame('MPIOPTPL', $rows[2]['bic']);

        self::assertSame('0010', $rows[3]['bank_code']);

        // Leading zeros survive as strings.
        foreach ($rows as $row) {
            self::assertIsString($row['bank_code']);
            self::assertMatchesRegularExpression('/^\d{4}$/', $row['bank_code']);
        }
    }

    public function testRowsRepairWindows1252MojibakeToUtf8(): void
    {
        // Simulate the common `pdftotext -layout` output WITHOUT `-enc UTF-8`:
        // accented characters come out as single-byte Windows-1252, not UTF-8.
        $utf8  = (string) file_get_contents(self::CLEAN_FIXTURE);
        $latin = (string) mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');

        // Sanity: the byte variant is genuinely NOT valid UTF-8 (so the
        // importer's cleanup branch is actually exercised).
        self::assertFalse(mb_check_encoding($latin, 'UTF-8'));

        $this->tempFixture = (string) tempnam(sys_get_temp_dir(), 'iban_pt_');
        file_put_contents($this->tempFixture, $latin);

        $rows = iterator_to_array($this->importer->rows($this->tempFixture), false);

        self::assertCount(4, $rows);
        // The accented name is recovered to proper UTF-8 despite the source
        // bytes being Windows-1252.
        self::assertSame('Caixa Geral de Depósitos, S.A.', $rows[1]['name']);
        self::assertSame('Caixa Económica Montepio Geral', $rows[2]['name']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/bportugal.txt'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableForTextWithNoDataRows(): void
    {
        $this->tempFixture = (string) tempnam(sys_get_temp_dir(), 'iban_pt_');
        file_put_contents($this->tempFixture, "Some header\nNo numeric codes here\n");

        $rows = iterator_to_array($this->importer->rows($this->tempFixture), false);

        self::assertSame([], $rows);
    }
}
