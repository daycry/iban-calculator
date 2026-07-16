<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\NbsSerbiaImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see NbsSerbiaImporter} in isolation (plain PHPUnit,
 * framework-free) against a reduced fixture reproducing the operator-prepared,
 * semicolon-delimited CSV built from the National Bank of Serbia's two PDFs
 * (`pregled_racuna_banka.pdf` + `pu_jedinstveni_id_brojevi.pdf`): a
 * `code;name;bic` layout, 3-digit code -> name + BIC. The pinned behaviours are
 * the header-by-name location (a preamble line above the header is ignored),
 * the 3-digit code kept as a string, and blank BIC -> null.
 *
 * @see \Daycry\Iban\Import\Importers\NbsSerbiaImporter
 */
final class NbsSerbiaImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/nbs_rs_sample.csv';

    private NbsSerbiaImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new NbsSerbiaImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('RS', $this->importer->countryCode());
        self::assertSame('nbs-rs', $this->importer->sourceId());
        self::assertStringContainsString('Serbia', $this->importer->sourceName());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://', $this->importer->sourceUrl());
    }

    public function testRowsMapThreeDigitCodesToNameAndBicLocatingTheHeaderByName(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // Four data rows; the preamble line is ignored (header located by name).
        self::assertCount(4, $rows);

        self::assertSame('105', $rows[0]['bank_code']);
        self::assertSame('AIK Banka a.d. Beograd', $rows[0]['name']);
        self::assertSame('AIKBRS22', $rows[0]['bic']);
        self::assertNull($rows[0]['branch_code']);

        self::assertSame('160', $rows[1]['bank_code']);
        self::assertSame('Banca Intesa a.d. Beograd', $rows[1]['name']);
        self::assertSame('DBDBRSBG', $rows[1]['bic']);

        self::assertSame('170', $rows[2]['bank_code']);
        self::assertSame('UniCredit Bank Srbija a.d. Beograd', $rows[2]['name']);
        self::assertSame('BACXRSBG', $rows[2]['bic']);

        // A row with no BIC maps to null (per the optional-bic contract).
        self::assertSame('999', $rows[3]['bank_code']);
        self::assertNull($rows[3]['bic']);

        foreach ($rows as $row) {
            self::assertIsString($row['bank_code']);
            self::assertMatchesRegularExpression('/^\d{3}$/', $row['bank_code']);
        }
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/rs.csv'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenNoRecognizableHeaderIsPresent(): void
    {
        $tmp = (string) tempnam(sys_get_temp_dir(), 'iban_rs_');
        file_put_contents($tmp, "just;some;noise\n1;2;3\n");

        try {
            $rows = iterator_to_array($this->importer->rows($tmp), false);
            self::assertSame([], $rows);
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
