<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\LuxembourgBankersAssociationImporter;
use PHPUnit\Framework\TestCase;
use Tests\_support\XlsxFixtureFactory;

/**
 * Exercises {@see LuxembourgBankersAssociationImporter} in isolation (plain
 * PHPUnit, framework-free) against a hand-crafted `.xlsx` fixture generated
 * on the fly with {@see XlsxFixtureFactory} -- reproducing the ABBL
 * register's confirmed layout: a title-row preamble, then the real
 * `Credit institution` / `IBAN Code ` / ` BIC Code` header row (WITH its
 * genuine stray whitespace, confirmed live via the rotating download on
 * 2026-07-11).
 *
 * @see \Daycry\Iban\Import\Importers\LuxembourgBankersAssociationImporter
 */
final class LuxembourgBankersAssociationImporterTest extends TestCase
{
    private LuxembourgBankersAssociationImporter $importer;

    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new LuxembourgBankersAssociationImporter();
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
        self::assertSame('LU', $this->importer->countryCode());
        self::assertSame('abbl', $this->importer->sourceId());
        self::assertSame('ABBL (Luxembourg Register of IBAN/BIC)', $this->importer->sourceName());
        self::assertSame('ABBL Luxembourg IBAN/BIC Register', $this->importer->license());
        // The stable landing page, NOT the rotating per-request download URL.
        self::assertSame(
            'https://www.abbl.lu/publications/abbl-luxembourg-register-of-iban-bic-codes/',
            $this->importer->sourceUrl(),
        );
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsParsesTheFixtureSkippingThePreambleAndNormalizesBic(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['ABBL' . "\n\n" . 'List of IBAN and BIC codes of Luxembourg credit institutions' . "\n"],
            ['Credit institution', 'IBAN Code ', ' BIC Code'],
            ["Banque et Caisse d'Epargne de l'Etat, Luxembourg (Spuerkeess)", '001', 'BCEE LU LL'],
            ['Banque Internationale à Luxembourg', '002', 'BILL LU LL'],
            ['Bank Julius Baer Europe S.A.', '032', 'BAERLULU'],
            ['', '', ''],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        // The preamble title row, the header row, and the blank row must all
        // be excluded -- only the 3 real data rows remain.
        self::assertCount(3, $rows);

        // The LU registry's own example IBAN (LU280019400644750000) bank code.
        $spuerkeess = $rows[0];
        self::assertSame('001', $spuerkeess['bank_code']);
        self::assertNull($spuerkeess['branch_code']);
        self::assertSame("Banque et Caisse d'Epargne de l'Etat, Luxembourg (Spuerkeess)", $spuerkeess['name']);
        self::assertSame('BCEELULL', $spuerkeess['bic']); // spaces stripped

        $bil = $rows[1];
        self::assertSame('002', $bil['bank_code']);
        self::assertSame('Banque Internationale à Luxembourg', $bil['name']); // UTF-8 accented char round trip
        self::assertSame('BILLLULL', $bil['bic']);

        // A BIC that's already space-free must pass through unchanged (aside
        // from uppercasing).
        $juliusBaer = $rows[2];
        self::assertSame('032', $juliusBaer['bank_code']);
        self::assertSame('BAERLULU', $juliusBaer['bic']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/abbl.xlsx'), false);

        self::assertSame([], $rows);
    }

    public function testRowsReturnsEmptyIterableWhenTheHeaderCannotBeLocated(): void
    {
        $this->fixturePath = XlsxFixtureFactory::write([
            ['Unexpected', 'Layout'],
            ['001', 'Something'],
        ]);

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        self::assertSame([], $rows);
    }
}
