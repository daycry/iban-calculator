<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\BancoDeEspanaImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see BancoDeEspanaImporter} in isolation (plain PHPUnit,
 * framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/bde_sample.csv` (a representative sample of Banco
 * de España's real, live MFI-list CSV, WITH a genuine leading UTF-8 BOM
 * byte, confirmed live 2026-07-11).
 *
 * @see \Daycry\Iban\Import\Importers\BancoDeEspanaImporter
 */
final class BancoDeEspanaImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/bde_sample.csv';

    private BancoDeEspanaImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new BancoDeEspanaImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('ES', $this->importer->countryCode());
        self::assertSame('bde', $this->importer->sourceId());
        self::assertSame('Banco de España', $this->importer->sourceName());
        self::assertSame('Banco de España', $this->importer->license());
        self::assertStringStartsWith('https://www.bde.es/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testFixtureReallyStartsWithAUtf8Bom(): void
    {
        // Sanity check on the fixture itself: without a genuine raw BOM
        // byte here, the BOM-strip assertions below would trivially pass
        // even if SixImporter's stripBom() were broken/missing.
        $raw = file_get_contents(self::FIXTURE);
        self::assertNotFalse($raw);
        self::assertStringStartsWith("\xEF\xBB\xBF", $raw);
    }

    public function testRowsStripsTheBomAndSkipsMoneyMarketFundCodes(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // 3 real credit-institution rows (0049/0182/2100); the FI2680
        // money-market-fund row must be skipped.
        self::assertCount(3, $rows);

        $santander = $rows[0];
        self::assertSame('0049', $santander['bank_code']); // kept as a zero-led STRING
        self::assertSame('Banco Santander, S.A.', $santander['name']); // comma-in-quotes preserved
        self::assertSame('Ps de Pereda, 9-12, 39004, Santander', $santander['address']);
        self::assertNull($santander['branch_code']);

        // No BOM/mojibake leakage into the first yielded field.
        self::assertStringNotContainsString("\xEF\xBB\xBF", $santander['bank_code']);
        self::assertStringNotContainsString('ï»¿', $santander['bank_code']);

        $bbva = $rows[1];
        self::assertSame('0182', $bbva['bank_code']);
        self::assertSame('Banco Bilbao Vizcaya Argentaria, S.A.', $bbva['name']);
        self::assertSame('Pz de San Nicolás, 4, 48005, Bilbao', $bbva['address']); // UTF-8 accented char round trip

        $caixabank = $rows[2];
        self::assertSame('2100', $caixabank['bank_code']);
        self::assertSame('Caixabank, S.A.', $caixabank['name']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/bde.csv'), false);

        self::assertSame([], $rows);
    }
}
