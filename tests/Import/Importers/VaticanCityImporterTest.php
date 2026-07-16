<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\VaticanCityImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see VaticanCityImporter} in isolation (plain PHPUnit,
 * framework-free). A CURATED importer: it yields a constant, project-authored
 * factual map (`bank_code -> name + BIC`) from `src/Import/Importers/data/va.php`
 * -- a single entry, since the Vatican's real bank universe is exactly one
 * institution (the IOR) -- independent of any `--file`/network input, so the
 * fixture *is* that data file (no HTML/CSV/text fixture needed).
 *
 * @see \Daycry\Iban\Import\Importers\VaticanCityImporter
 */
final class VaticanCityImporterTest extends TestCase
{
    private VaticanCityImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new VaticanCityImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumentedAndMarksCuratedProvenance(): void
    {
        self::assertSame('VA', $this->importer->countryCode());
        self::assertSame('vatican', $this->importer->sourceId());
        self::assertStringContainsString('IOR', $this->importer->sourceName());
        self::assertSame('curated (factual, non-copyrightable)', $this->importer->license());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://', $this->importer->sourceUrl());
    }

    public function testRowsYieldTheSingleCuratedIorEntryWithAThreeDigitStringCode(): void
    {
        $rows = iterator_to_array($this->importer->rows(), false);

        // The Vatican's whole bank universe is one institution (the IOR).
        self::assertCount(1, $rows);

        self::assertSame('001', $rows[0]['bank_code']);
        self::assertSame('Istituto per le Opere di Religione (IOR)', $rows[0]['name']);
        self::assertSame('IOPRVAVX', $rows[0]['bic']);
        self::assertNull($rows[0]['branch_code']);

        // The leading zeros must survive as a string, never coerced to int.
        self::assertIsString($rows[0]['bank_code']);
        self::assertMatchesRegularExpression('/^\d{3}$/', $rows[0]['bank_code']);
    }

    public function testRowsIgnoreTheLocalFileArgumentBecauseTheDataIsCurated(): void
    {
        // A curated importer's data is constant: passing a bogus $localFile
        // must not change (or suppress) the yielded rows.
        $withFile = iterator_to_array($this->importer->rows('/nonexistent/va.pdf'), false);
        $noFile   = iterator_to_array($this->importer->rows(), false);

        self::assertSame($noFile, $withFile);
        self::assertCount(1, $withFile);
    }
}
