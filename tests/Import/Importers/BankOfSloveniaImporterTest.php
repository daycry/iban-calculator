<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\BankOfSloveniaImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see BankOfSloveniaImporter} in isolation (plain PHPUnit,
 * framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/bsi_sample.csv` (a representative sample of the
 * Bank of Slovenia's real, live "list of identifiers of PSP" CSV, encoded
 * as genuine Windows-1250 bytes, confirmed live 2026-07-11), plus one
 * fixture-only row for NATIONAL ID `26330` -- the SI registry's own
 * canonical example bank code (see `src/Registry/data/countries.php`'s
 * `'SI' => 'example' => 'SI56263300012039086'`) that is NOT present in the
 * live snapshot, added here so `ImportRunnerImportersTest`'s resolve()
 * proof has a matching seeded row.
 *
 * @see \Daycry\Iban\Import\Importers\BankOfSloveniaImporter
 */
final class BankOfSloveniaImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/bsi_sample.csv';

    private BankOfSloveniaImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new BankOfSloveniaImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('SI', $this->importer->countryCode());
        self::assertSame('bsi', $this->importer->sourceId());
        self::assertSame('Bank of Slovenia', $this->importer->sourceName());
        self::assertSame('Bank of Slovenia (cite source, no changes)', $this->importer->license());
        self::assertStringStartsWith('https://www.bsi.si/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testFixtureIsGenuineWindows1250Bytes(): void
    {
        // Sanity check on the fixture itself: it must NOT already be valid
        // UTF-8, so the parsing test below genuinely exercises the
        // Windows-1250 -> UTF-8 fallback conversion rather than a no-op.
        $raw = file_get_contents(self::FIXTURE);
        self::assertNotFalse($raw);
        self::assertFalse(mb_check_encoding($raw, 'UTF-8'));
    }

    public function testRowsParsesTheFixtureAndSkipsTheHeaderAndCommentRows(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has a header row + a `#`-prefixed explanatory comment
        // row (both must be skipped) followed by 4 real data rows.
        self::assertCount(4, $rows);

        $bankaSlovenije = $rows[0];
        self::assertSame('01000', $bankaSlovenije['bank_code']);
        self::assertSame('BANKA SLOVENIJE', $bankaSlovenije['name']);
        self::assertSame('SLOVENSKA 35', $bankaSlovenije['address']);
        self::assertSame('LJUBLJANA', $bankaSlovenije['city']);
        self::assertSame('BSLJSI2XXXX', $bankaSlovenije['bic']);
        self::assertNull($bankaSlovenije['branch_code']);

        $intesa = $rows[1];
        self::assertSame('10100', $intesa['bank_code']);
        self::assertSame('BANKA INTESA SANPAOLO D.D.', $intesa['name']);
        self::assertSame('PRISTANIŠKA 14', $intesa['address']); // UTF-8 accented char round trip
        self::assertSame('KOPER', $intesa['city']);

        $unicredit = $rows[2];
        self::assertSame('29000', $unicredit['bank_code']);
        self::assertSame('UNICREDIT BANKA SLOVENIJA D.D.', $unicredit['name']);
        self::assertSame('AMERIŠKA ULICA 2', $unicredit['address']);

        // The fixture-only row added for the registry's IBAN example bank code.
        $example = $rows[3];
        self::assertSame('26330', $example['bank_code']);
        self::assertSame('PRIMER BANKA D.D. (IBAN example fixture row)', $example['name']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/bsi.csv'), false);

        self::assertSame([], $rows);
    }
}
