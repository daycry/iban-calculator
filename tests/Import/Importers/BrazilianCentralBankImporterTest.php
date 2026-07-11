<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\BrazilianCentralBankImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see BrazilianCentralBankImporter} in isolation (plain
 * PHPUnit, framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/bcb_sample.csv` (a representative sample of Banco
 * Central do Brasil's real, live STR-participant CSV, confirmed live
 * 2026-07-11).
 *
 * @see \Daycry\Iban\Import\Importers\BrazilianCentralBankImporter
 */
final class BrazilianCentralBankImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/bcb_sample.csv';

    private BrazilianCentralBankImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new BrazilianCentralBankImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('BR', $this->importer->countryCode());
        self::assertSame('bcb', $this->importer->sourceId());
        self::assertSame('Banco Central do Brasil', $this->importer->sourceName());
        self::assertSame('Banco Central do Brasil (ODbL)', $this->importer->license());
        self::assertStringStartsWith('https://www.bcb.gov.br/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsMapsIspbToBankCodeAndPrefersNomeExtenso(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has 3 data rows: Banco do Brasil (ISPB 00000000),
        // Caixa Econômica Federal (ISPB 00360305) and a fixture-only row
        // (ISPB 00999999) with a blank Nome_Extenso, testing the
        // Nome_Reduzido fallback.
        self::assertCount(3, $rows);

        $bb = $rows[0];
        self::assertSame('00000000', $bb['bank_code']);
        self::assertNull($bb['branch_code']);
        self::assertSame('Banco do Brasil S.A.', $bb['name']);

        $caixa = $rows[1];
        self::assertSame('00360305', $caixa['bank_code']);
        self::assertNull($caixa['branch_code']);
        self::assertSame('CAIXA ECONOMICA FEDERAL', $caixa['name']);

        $fixture = $rows[2];
        self::assertSame('00999999', $fixture['bank_code']);
        self::assertNull($fixture['branch_code']);
        // Nome_Extenso is blank on this row -- name must fall back to Nome_Reduzido.
        self::assertSame('FIXTURE BANK LTDA (fixture — BR registry example bank code)', $fixture['name']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/bcb.csv'), false);

        self::assertSame([], $rows);
    }
}
