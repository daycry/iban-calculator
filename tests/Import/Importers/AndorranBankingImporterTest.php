<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\AndorranBankingImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see AndorranBankingImporter} in isolation (plain PHPUnit,
 * framework-free). This is the catalog's first CURATED importer: it yields a
 * constant, project-authored factual map (`bank_code -> name + BIC`) from
 * `src/Import/Importers/data/ad.php`, independent of any `--file`/network
 * input, so the fixture *is* that data file (no HTML/CSV/text fixture needed).
 *
 * @see \Daycry\Iban\Import\Importers\AndorranBankingImporter
 */
final class AndorranBankingImporterTest extends TestCase
{
    private AndorranBankingImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new AndorranBankingImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumentedAndMarksCuratedProvenance(): void
    {
        self::assertSame('AD', $this->importer->countryCode());
        self::assertSame('andorran-banking', $this->importer->sourceId());
        self::assertStringContainsString('Andorran Banking', $this->importer->sourceName());
        self::assertSame('curated (factual, non-copyrightable)', $this->importer->license());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://', $this->importer->sourceUrl());
    }

    public function testRowsYieldTheCuratedMapWithFourDigitStringCodesAndBics(): void
    {
        $rows = iterator_to_array($this->importer->rows(), false);

        // 3 banks / 4 codes: Andbank 0001, Creand 0003, MoraBanc 0007 + 0008.
        self::assertCount(4, $rows);

        self::assertSame('0001', $rows[0]['bank_code']);
        self::assertSame('Andorra Banc Agrícol Reig, S.A. (Andbank)', $rows[0]['name']);
        self::assertSame('BACAADAD', $rows[0]['bic']);
        self::assertNull($rows[0]['branch_code']);

        self::assertSame('0003', $rows[1]['bank_code']);
        self::assertSame('Crèdit Andorrà, S.A. (Creand)', $rows[1]['name']);
        self::assertSame('CRDAADAD', $rows[1]['bic']);

        // MoraBanc holds two codes, both BSAAADAD.
        self::assertSame('0007', $rows[2]['bank_code']);
        self::assertSame('BSAAADAD', $rows[2]['bic']);
        self::assertSame('0008', $rows[3]['bank_code']);
        self::assertSame('BSAAADAD', $rows[3]['bic']);

        // Leading zeros must survive as strings, never coerced to int.
        foreach ($rows as $row) {
            self::assertIsString($row['bank_code']);
            self::assertMatchesRegularExpression('/^\d{4}$/', $row['bank_code']);
        }
    }

    public function testRowsIgnoreTheLocalFileArgumentBecauseTheDataIsCurated(): void
    {
        // A curated importer's data is constant: passing a bogus $localFile
        // must not change (or suppress) the yielded rows.
        $withFile = iterator_to_array($this->importer->rows('/nonexistent/ad.pdf'), false);
        $noFile   = iterator_to_array($this->importer->rows(), false);

        self::assertSame($noFile, $withFile);
        self::assertCount(4, $withFile);
    }
}
