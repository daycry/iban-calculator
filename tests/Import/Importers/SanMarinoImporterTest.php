<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\SanMarinoImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see SanMarinoImporter} in isolation (plain PHPUnit,
 * framework-free). A CURATED importer: it yields a constant, project-authored
 * factual map (5-digit ABI `bank_code -> name + BIC`) from
 * `src/Import/Importers/data/sm.php` -- the four Sammarinese banks -- independent
 * of any `--file`/network input, so the fixture *is* that data file (no
 * HTML/CSV/text fixture needed).
 *
 * @see \Daycry\Iban\Import\Importers\SanMarinoImporter
 */
final class SanMarinoImporterTest extends TestCase
{
    private SanMarinoImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new SanMarinoImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumentedAndMarksCuratedProvenance(): void
    {
        self::assertSame('SM', $this->importer->countryCode());
        self::assertSame('bcsm', $this->importer->sourceId());
        self::assertStringContainsString('San Marino', $this->importer->sourceName());
        self::assertSame('curated (factual, non-copyrightable)', $this->importer->license());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://', $this->importer->sourceUrl());
    }

    public function testRowsYieldTheCuratedMapWithFiveDigitStringAbiCodesAndBics(): void
    {
        $rows = iterator_to_array($this->importer->rows(), false);

        // Four Sammarinese banks.
        self::assertCount(4, $rows);

        self::assertSame('03034', $rows[0]['bank_code']);
        self::assertSame('Banca Agricola Commerciale della Repubblica di San Marino', $rows[0]['name']);
        self::assertSame('BASMSMSM', $rows[0]['bic']);
        self::assertNull($rows[0]['branch_code']);

        self::assertSame('08540', $rows[1]['bank_code']);
        self::assertSame('Banca di San Marino', $rows[1]['name']);
        self::assertSame('MAOISMSM', $rows[1]['bic']);

        self::assertSame('03287', $rows[2]['bank_code']);
        self::assertSame('Banca Sammarinese di Investimento', $rows[2]['name']);
        self::assertSame('BSDISMSD', $rows[2]['bic']);

        self::assertSame('06067', $rows[3]['bank_code']);
        self::assertSame('Cassa di Risparmio della Repubblica di San Marino', $rows[3]['name']);
        self::assertSame('CSSMSMSM', $rows[3]['bic']);

        // Leading zeros must survive as 5-digit strings, never coerced to int.
        foreach ($rows as $row) {
            self::assertIsString($row['bank_code']);
            self::assertMatchesRegularExpression('/^\d{5}$/', $row['bank_code']);
        }
    }

    public function testRowsIgnoreTheLocalFileArgumentBecauseTheDataIsCurated(): void
    {
        // A curated importer's data is constant: passing a bogus $localFile
        // must not change (or suppress) the yielded rows.
        $withFile = iterator_to_array($this->importer->rows('/nonexistent/sm.html'), false);
        $noFile   = iterator_to_array($this->importer->rows(), false);

        self::assertSame($noFile, $withFile);
        self::assertCount(4, $withFile);
    }
}
