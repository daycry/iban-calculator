<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\NationalBankOfBelgiumImporter;
use PHPUnit\Framework\TestCase;
use Tests\_support\XlsxFixtureFactory;

/**
 * Exercises {@see NationalBankOfBelgiumImporter} in isolation (plain
 * PHPUnit, framework-free) against a hand-crafted `.xlsx` fixture generated
 * on the fly with {@see XlsxFixtureFactory} -- reproducing the live NBB
 * "Protocol" full list's confirmed layout: a version-stamp preamble row,
 * then the real `T_Identification_Number`/`Biccode`/`T_Institutions_*`
 * header row (confirmed live 2026-07-11).
 *
 * @see \Daycry\Iban\Import\Importers\NationalBankOfBelgiumImporter
 */
final class NationalBankOfBelgiumImporterTest extends TestCase
{
    private NationalBankOfBelgiumImporter $importer;

    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new NationalBankOfBelgiumImporter();
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
        self::assertSame('BE', $this->importer->countryCode());
        self::assertSame('nbb', $this->importer->sourceId());
        self::assertSame('National Bank of Belgium', $this->importer->sourceName());
        self::assertSame('National Bank of Belgium', $this->importer->license());
        self::assertStringStartsWith('https://www.nbb.be/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsParsesTheFixtureSkippingThePreambleAndFallsBackThroughNameColumns(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['Version 06/05/2026'],
            [
                'T_Identification_Number',
                'Biccode',
                'T_Institutions_Dutch',
                'T_Institutions_French',
                'T_Institutions_German',
                'T_Institutions_English',
            ],
            ['000', 'GEBA BE BB', 'BNP Paribas Fortis', '', '', ''],
            ['539', 'GEBA BE BB', 'Voorbeeldbank NV', 'Banque Exemple SA', '', 'Example Bank'],
            ['510', 'VAPE BE 22', 'VAN DE PUT & CO Privaatbankiers', 'VAN DE PUT & CO Banquiers Privés', '', ''],
            ['171', 'CPHB BE 75', '', 'Banque CPH', '', ''],
            ['995', 'N/A', 'VRIJ', 'LIBRE', '', ''],
            ['000-099', 'GEBA BE BB', 'Grouped range row', '', '', ''],
            ['', '', '', '', '', ''],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        // 5 real 3-digit-code rows; the grouped-range row ('000-099') and the
        // fully blank row must both be skipped.
        self::assertCount(5, $rows);

        $bnpParibasFortis = $rows[0];
        self::assertSame('000', $bnpParibasFortis['bank_code']);
        self::assertNull($bnpParibasFortis['branch_code']);
        self::assertSame('BNP Paribas Fortis', $bnpParibasFortis['name']); // NL fallback (EN/FR/DE all empty)
        self::assertSame('GEBABEBB', $bnpParibasFortis['bic']); // spaces stripped

        $exampleBank = $rows[1];
        self::assertSame('539', $exampleBank['bank_code']);
        self::assertSame('Example Bank', $exampleBank['name']); // EN preferred over NL/FR
        self::assertSame('GEBABEBB', $exampleBank['bic']);

        $vanDePut = $rows[2];
        self::assertSame('510', $vanDePut['bank_code']);
        self::assertSame('VAN DE PUT & CO Privaatbankiers', $vanDePut['name']); // NL fallback (EN empty)

        $cph = $rows[3];
        self::assertSame('171', $cph['bank_code']);
        self::assertSame('Banque CPH', $cph['name']); // FR fallback (EN/NL empty)

        $unassigned = $rows[4];
        self::assertSame('995', $unassigned['bank_code']);
        self::assertSame('VRIJ', $unassigned['name']);
        self::assertNull($unassigned['bic']); // 'N/A' maps to null
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/nbb.xlsx'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenTheHeaderCannotBeLocated(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['Unexpected', 'Layout'],
            ['000', 'Something'],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertSame([], $rows);
    }
}
