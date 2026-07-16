<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\CentralBankOfCyprusImporter;
use PHPUnit\Framework\TestCase;
use Tests\_support\XlsxFixtureFactory;

/**
 * Exercises {@see CentralBankOfCyprusImporter} in isolation (plain PHPUnit,
 * framework-free) against a hand-crafted `.xlsx` fixture generated with
 * {@see XlsxFixtureFactory}, reproducing the Central Bank of Cyprus
 * `CIs_and_EMIs_BICs_*.xlsx` layout: a title preamble row, then a header row
 * with the literal `Bank identifiers used in IBAN` column, mapping the
 * 3-digit code (zero-padded when the source dropped a leading zero) to name +
 * BIC. The live landing-scrape + WAF User-Agent path is not exercised (repo
 * convention -- no importer's network path is tested).
 *
 * @see \Daycry\Iban\Import\Importers\CentralBankOfCyprusImporter
 */
final class CentralBankOfCyprusImporterTest extends TestCase
{
    private CentralBankOfCyprusImporter $importer;

    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new CentralBankOfCyprusImporter();
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
        self::assertSame('CY', $this->importer->countryCode());
        self::assertSame('cbc', $this->importer->sourceId());
        self::assertStringContainsString('Cyprus', $this->importer->sourceName());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://www.centralbank.cy/', $this->importer->sourceUrl());
    }

    public function testRowsMapTheThreeDigitCodeColumnToNameAndBicZeroPadding(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['Credit Institutions and EMIs - Bank identifiers used in IBAN (April 2025)'],
            ['Name of Credit Institution', 'Bank identifiers used in IBAN', 'BIC'],
            ['Bank of Cyprus Public Company Ltd', '002', 'BCYPCY2N'],
            ['Hellenic Bank Public Company Ltd', '5', 'HEBACY2N'], // dropped leading zeros
            ['Some EMI Ltd', '901', 'SOMECY2NXXX'],
            ['', '', ''],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertCount(3, $rows);

        self::assertSame('002', $rows[0]['bank_code']);
        self::assertSame('Bank of Cyprus Public Company Ltd', $rows[0]['name']);
        self::assertSame('BCYPCY2N', $rows[0]['bic']);
        self::assertNull($rows[0]['branch_code']);

        // '5' must zero-pad to the 3-digit '005'.
        self::assertSame('005', $rows[1]['bank_code']);
        self::assertSame('Hellenic Bank Public Company Ltd', $rows[1]['name']);

        self::assertSame('901', $rows[2]['bank_code']); // EMI range
        self::assertSame('SOMECY2NXXX', $rows[2]['bic']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/cy.xlsx'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenTheHeaderCannotBeLocated(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['Unexpected', 'Layout'],
            ['002', 'Something'],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertSame([], $rows);
    }
}
