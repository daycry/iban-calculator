<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\BitsNorwayImporter;
use PHPUnit\Framework\TestCase;
use Tests\_support\XlsxFixtureFactory;

/**
 * Exercises {@see BitsNorwayImporter} in isolation (plain PHPUnit,
 * framework-free) against a hand-crafted `.xlsx` fixture generated on the fly
 * with {@see XlsxFixtureFactory} -- reproducing the live Bits AS IBAN
 * bank-identifier list's confirmed layout: no preamble, header row 1 with the
 * real `Bank identifier`/`BIC`/`Bank` columns (confirmed live 2026-07-11),
 * including the legitimate no-dedup repetition of a single legal entity
 * across several distinct 4-digit identifiers.
 *
 * @see \Daycry\Iban\Import\Importers\BitsNorwayImporter
 */
final class BitsNorwayImporterTest extends TestCase
{
    private BitsNorwayImporter $importer;

    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new BitsNorwayImporter();
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
        self::assertSame('NO', $this->importer->countryCode());
        self::assertSame('bits', $this->importer->sourceId());
        self::assertSame('Bits AS (Norway)', $this->importer->sourceName());
        self::assertSame('Bits AS (Norway)', $this->importer->license());
        self::assertStringStartsWith('https://www.bits.no/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsYieldsEveryIdentifierWithoutDedupingRepeatedBankNames(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['Bank identifier', 'BIC', 'Bank'],
            ['0500', 'DNBANOKK', 'DNB Bank ASA'],
            // Same legal entity, a DIFFERENT 4-digit identifier -- both must
            // be imported as independent rows (no dedup for this source).
            ['0519', 'DNBANOKK', 'DNB Bank ASA '],
            ['8601', 'DABANO22', 'Danske Bank (fixture — NO registry example bank code)'],
            // Malformed/footer rows -- not a 4-digit identifier -- must be
            // skipped entirely.
            ['', '', ''],
            ['Kilde: Bits', '', ''],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertCount(3, $rows);

        $dnb1 = $rows[0];
        self::assertSame('0500', $dnb1['bank_code']);
        self::assertNull($dnb1['branch_code']);
        self::assertSame('DNB Bank ASA', $dnb1['name']);
        self::assertSame('DNBANOKK', $dnb1['bic']);

        $dnb2 = $rows[1];
        self::assertSame('0519', $dnb2['bank_code']);
        self::assertSame('DNB Bank ASA', $dnb2['name']); // trailing whitespace trimmed
        self::assertSame('DNBANOKK', $dnb2['bic']);

        $danske = $rows[2];
        self::assertSame('8601', $danske['bank_code']);
        self::assertSame('Danske Bank (fixture — NO registry example bank code)', $danske['name']);
        self::assertSame('DABANO22', $danske['bic']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/bits.xlsx'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenTheHeaderCannotBeLocated(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['Unexpected', 'Layout'],
            ['0500', 'Something'],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertSame([], $rows);
    }
}
