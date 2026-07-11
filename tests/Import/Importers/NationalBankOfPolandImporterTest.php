<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\NationalBankOfPolandImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see NationalBankOfPolandImporter} in isolation (plain
 * PHPUnit, framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/nbp_sample.xml` -- a representative, structurally
 * faithful sample of Narodowy Bank Polski's real, live EWIB XML feed
 * (confirmed live 2026-07-11), trimmed to 3 institutions each carrying one
 * or more `<Jednostka>`/`<NumerRozliczeniowy>` combinations.
 *
 * @see \Daycry\Iban\Import\Importers\NationalBankOfPolandImporter
 */
final class NationalBankOfPolandImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/nbp_sample.xml';

    private NationalBankOfPolandImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new NationalBankOfPolandImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('PL', $this->importer->countryCode());
        self::assertSame('nbp', $this->importer->sourceId());
        self::assertSame('Narodowy Bank Polski (EWIB)', $this->importer->sourceName());
        self::assertSame(
            'Narodowy Bank Polski (public sector information, free reuse)',
            $this->importer->license(),
        );
        self::assertStringStartsWith('https://ewib.nbp.pl/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsRollsUpPerBranchSettlementCodesToOneRowPerThreeDigitBankCode(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has 5 raw <NumerRozliczeniowy> settlement codes across
        // 3 institutions, but NBP and Erste Bank Polska each publish 2 codes
        // sharing the same 3-digit prefix -- so only 3 unique bank_code rows
        // are yielded.
        self::assertCount(3, $rows);

        $nbp = $rows[0];
        self::assertSame('101', $nbp['bank_code']);
        self::assertSame('Narodowy Bank Polski', $nbp['name']);
        self::assertNull($nbp['branch_code']);

        $bph = $rows[1];
        self::assertSame('106', $bph['bank_code']);
        self::assertSame('Bank BPH Spółka Akcyjna', $bph['name']); // UTF-8 accented char round trip
        self::assertNull($bph['branch_code']);

        // The registry's own example bank code (see the IBAN example
        // PL61109010140000071219812874, clearing code 10901014) -- the
        // FIRST settlement code seen for this institution (10900004, its
        // "Centrala" unit) wins; the branch-level 10901014 code is deduped
        // away, but both share the same 3-digit prefix '109'.
        $erste = $rows[2];
        self::assertSame('109', $erste['bank_code']);
        self::assertSame('Erste Bank Polska Spółka Akcyjna', $erste['name']);
        self::assertNull($erste['branch_code']);

        $bankCodes = array_column($rows, 'bank_code');
        self::assertSame(['101', '106', '109'], $bankCodes);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/nbp.xml'), false);

        self::assertSame([], $rows);
    }
}
