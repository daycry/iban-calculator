<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\NationalBankOfKazakhstanImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see NationalBankOfKazakhstanImporter} in isolation (plain
 * PHPUnit, framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/nbk_sample.json` -- built from a DOCUMENTED-SHAPE
 * ASSUMPTION about data.egov.kz's bank directory dataset (the portal
 * requires an API key this package does not ship, so the exact live JSON
 * keys have not been confirmed -- see
 * {@see NationalBankOfKazakhstanImporter}'s class docblock).
 *
 * @see \Daycry\Iban\Import\Importers\NationalBankOfKazakhstanImporter
 */
final class NationalBankOfKazakhstanImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/nbk_sample.json';

    private NationalBankOfKazakhstanImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new NationalBankOfKazakhstanImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('KZ', $this->importer->countryCode());
        self::assertSame('nbk', $this->importer->sourceId());
        self::assertSame('National Bank of Kazakhstan', $this->importer->sourceName());
        self::assertSame('National Bank of Kazakhstan (open data)', $this->importer->license());
        self::assertStringStartsWith('https://data.egov.kz/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsMapsCodeBicNameToBankLevelRows(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        self::assertCount(3, $rows);

        // The registry's own example bank code (see the IBAN example
        // KZ86125KZT5004100100 -> bank code '125').
        $forteBank = $rows[0];
        self::assertSame('125', $forteBank['bank_code']);
        self::assertSame('IRTYKZKA', $forteBank['bic']);
        self::assertSame('ForteBank JSC (fixture — KZ registry example bank code)', $forteBank['name']);
        self::assertNull($forteBank['branch_code']);

        $kaspi = $rows[1];
        self::assertSame('722', $kaspi['bank_code']);
        self::assertSame('CASPKZKA', $kaspi['bic']);
        self::assertSame('Kaspi Bank JSC', $kaspi['name']);

        $halyk = $rows[2];
        self::assertSame('196', $halyk['bank_code']);
        self::assertSame('HSBKKZKX', $halyk['bic']);
        self::assertSame('Halyk Bank of Kazakhstan JSC', $halyk['name']);
    }

    public function testRowsFallsBackToBikFieldForBic(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'iban_nbk_fallback_');
        self::assertIsString($path);
        file_put_contents($path, json_encode([
            ['code' => '500', 'bik' => 'testbank1', 'name' => 'Test Bank'],
        ]));

        try {
            $rows = iterator_to_array($this->importer->rows($path), false);

            self::assertCount(1, $rows);
            self::assertSame('500', $rows[0]['bank_code']);
            self::assertSame('TESTBANK1', $rows[0]['bic']); // upper-cased
            self::assertSame('Test Bank', $rows[0]['name']);
        } finally {
            unlink($path);
        }
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/nbk.json'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableForMalformedJson(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'iban_nbk_malformed_');
        self::assertIsString($path);
        file_put_contents($path, '[ this is not valid JSON');

        try {
            $rows = iterator_to_array($this->importer->rows($path), false);

            self::assertSame([], $rows);
        } finally {
            unlink($path);
        }
    }
}
