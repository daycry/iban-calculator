<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\NationalBankOfMoldovaImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see NationalBankOfMoldovaImporter} in isolation (plain
 * PHPUnit, framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/bnm_sample.xml` -- a representative, structurally
 * faithful sample of the National Bank of Moldova's real, live "licensed
 * banks" XML feed (confirmed live 2026-07-11, including its
 * `encoding="latin1"` prolog).
 *
 * @see \Daycry\Iban\Import\Importers\NationalBankOfMoldovaImporter
 */
final class NationalBankOfMoldovaImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/bnm_sample.xml';

    private NationalBankOfMoldovaImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new NationalBankOfMoldovaImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('MD', $this->importer->countryCode());
        self::assertSame('bnm', $this->importer->sourceId());
        self::assertSame('National Bank of Moldova', $this->importer->sourceName());
        self::assertSame('National Bank of Moldova', $this->importer->license());
        self::assertStringStartsWith('https://www.bnm.md/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testFixtureDeclaresTheRealSourcesLatin1Encoding(): void
    {
        $raw = file_get_contents(self::FIXTURE);
        self::assertNotFalse($raw);
        self::assertStringContainsString('encoding="latin1"', $raw);
    }

    public function testRowsParsesTheFixtureSkippingSubAccountRowsWithNoIbanIdentifier(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has 6 <Participant> entries, but the first has an
        // empty IBANIdentifier (a branch/sub-account row) and must be skipped.
        self::assertCount(5, $rows);

        // The registry's own example bank code (see the IBAN example
        // MD24AG000225100013104168).
        $maib = $rows[0];
        self::assertSame('AG', $maib['bank_code']);
        self::assertSame("BC'MAIB'S.A.", $maib['name']);
        self::assertSame('AGRNMD2X', $maib['bic']);
        self::assertNull($maib['branch_code']);

        $bnm = $rows[1];
        self::assertSame('NB', $bnm['bank_code']);
        self::assertSame('BANCA NATIONALA A MOLDOVEI', $bnm['name']);
        self::assertSame('NBMDMD2X', $bnm['bic']);

        $victoriabank = $rows[2];
        self::assertSame('VI', $victoriabank['bank_code']);
        self::assertSame("B.C.'VICTORIABANK'S.A.", $victoriabank['name']);
        self::assertSame('VICBMD2X', $victoriabank['bic']);

        // The sub-account/branch entry (empty IBANIdentifier) must not have
        // produced its own row.
        $bankCodes = array_column($rows, 'bank_code');
        self::assertSame(['AG', 'NB', 'VI', 'AR', 'TI'], $bankCodes);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/bnm.xml'), false);

        self::assertSame([], $rows);
    }
}
