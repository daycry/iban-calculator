<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\CroatianNationalBankImporter;
use PHPUnit\Framework\TestCase;
use Tests\_support\XlsxFixtureFactory;

/**
 * Exercises {@see CroatianNationalBankImporter} in isolation (plain
 * PHPUnit, framework-free) against a hand-crafted `.xlsx` fixture generated
 * on the fly with {@see XlsxFixtureFactory} -- reproducing the live HNB
 * "Payment service provider codes" list's confirmed layout: a two-row
 * preamble (title + blank), then the real "Payment service provider" /
 * "Code" / "SWIFT adresa\n(BIC)" header row (confirmed live 2026-07-11).
 *
 * @see \Daycry\Iban\Import\Importers\CroatianNationalBankImporter
 */
final class CroatianNationalBankImporterTest extends TestCase
{
    private CroatianNationalBankImporter $importer;

    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new CroatianNationalBankImporter();
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
        self::assertSame('HR', $this->importer->countryCode());
        self::assertSame('hnb', $this->importer->sourceId());
        self::assertSame('Croatian National Bank', $this->importer->sourceName());
        self::assertSame('Croatian National Bank (cite source, no changes)', $this->importer->license());
        self::assertStringStartsWith('https://www.hnb.hr/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsParsesTheFixtureSkippingThePreambleAndFooter(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['', 'Payment service provider codes', '', '', ''],
            ['', '', '', '', ''],
            ['', 'Payment service provider', 'Code', "SWIFT adresa\n(BIC)", ''],
            ['', 'ADDIKO BANK d.d. Zagreb', '2500009', 'HAAB HR 22', ''],
            ['', 'AIRCASH d.o.o. Zagreb', '4501006', '', ''],
            ['', 'HRVATSKA NARODNA BANKA', '1001005', 'NBHR HR 2X ', ''],
            ['', '', '', '', ''],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        // The title row, blank row, header row, and trailing blank row must
        // all be excluded -- only the 3 real data rows remain.
        self::assertCount(3, $rows);

        $addiko = $rows[0];
        self::assertSame('2500009', $addiko['bank_code']);
        self::assertNull($addiko['branch_code']);
        self::assertSame('ADDIKO BANK d.d. Zagreb', $addiko['name']);
        self::assertSame('HAABHR22', $addiko['bic']); // spaces stripped

        // A row with no published BIC must yield a null `bic`, not an empty string.
        $aircash = $rows[1];
        self::assertSame('4501006', $aircash['bank_code']);
        self::assertSame('AIRCASH d.o.o. Zagreb', $aircash['name']);
        self::assertNull($aircash['bic']);

        // The HNB registry's own example IBAN (HR1210010051863000160) bank code.
        $hnb = $rows[2];
        self::assertSame('1001005', $hnb['bank_code']);
        self::assertSame('HRVATSKA NARODNA BANKA', $hnb['name']);
        self::assertSame('NBHRHR2X', $hnb['bic']); // trailing space + internal spaces stripped
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/hnb.xlsx'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenTheHeaderCannotBeLocated(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['Unexpected', 'Layout'],
            ['2500009', 'Something'],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertSame([], $rows);
    }
}
