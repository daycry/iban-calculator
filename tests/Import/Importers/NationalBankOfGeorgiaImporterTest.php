<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\NationalBankOfGeorgiaImporter;
use PHPUnit\Framework\TestCase;
use Tests\_support\XlsxFixtureFactory;

/**
 * Exercises {@see NationalBankOfGeorgiaImporter} in isolation (plain
 * PHPUnit, framework-free) against a hand-crafted `.xlsx` fixture generated
 * on the fly with {@see XlsxFixtureFactory} -- reproducing the live NBG
 * RTGS-participants list's confirmed layout: no preamble, header row 1 with
 * the real, GEORGIAN-ONLY (no English labels, confirmed live 2026-07-11)
 * `მონაწილის დასახელება` / `RTGS მონაწილის კოდი` / `IBAN კოდი` columns,
 * located by the Latin-script `RTGS`/`IBAN` substrings embedded in the
 * otherwise-Georgian header labels, plus the stray leading whitespace the
 * live file carries on its RTGS/IBAN-code cells.
 *
 * @see \Daycry\Iban\Import\Importers\NationalBankOfGeorgiaImporter
 */
final class NationalBankOfGeorgiaImporterTest extends TestCase
{
    private NationalBankOfGeorgiaImporter $importer;

    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new NationalBankOfGeorgiaImporter();
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
        self::assertSame('GE', $this->importer->countryCode());
        self::assertSame('nbg', $this->importer->sourceId());
        self::assertSame('National Bank of Georgia', $this->importer->sourceName());
        self::assertSame('National Bank of Georgia', $this->importer->license());
        self::assertStringStartsWith('https://nbg.gov.ge/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsLocatesTheGeorgianOnlyHeaderAndTrimsLeadingWhitespace(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['მონაწილის დასახელება', 'RTGS მონაწილის კოდი', 'IBAN კოდი'],
            ['საქართველოს ეროვნული ბანკი (fixture — GE registry example bank code)', ' BNLNGE22', 'NB'],
            [' სს "საქართველოს ბანკი"', ' BAGAGE22', ' BG'],
            ['  სს "თიბისი ბანკი"', ' TBCBGE22', ' TB'],
            // Malformed/blank row -- no IBAN code -- must be skipped.
            ['', '', ''],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertCount(3, $rows);

        $nbg = $rows[0];
        self::assertSame('NB', $nbg['bank_code']);
        self::assertNull($nbg['branch_code']);
        self::assertSame('საქართველოს ეროვნული ბანკი (fixture — GE registry example bank code)', $nbg['name']); // UTF-8 round trip
        self::assertSame('BNLNGE22', $nbg['bic']); // leading space trimmed

        $bog = $rows[1];
        self::assertSame('BG', $bog['bank_code']); // leading space trimmed
        self::assertSame('სს "საქართველოს ბანკი"', $bog['name']); // leading whitespace trimmed
        self::assertSame('BAGAGE22', $bog['bic']);

        $tbc = $rows[2];
        self::assertSame('TB', $tbc['bank_code']);
        self::assertSame('სს "თიბისი ბანკი"', $tbc['name']);
        self::assertSame('TBCBGE22', $tbc['bic']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/nbg.xlsx'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenTheHeaderCannotBeLocated(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['Unexpected', 'Layout'],
            ['NB', 'Something'],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertSame([], $rows);
    }
}
