<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\CentralBankOfAzerbaijanImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see CentralBankOfAzerbaijanImporter} in isolation (plain
 * PHPUnit, framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/cbar_sample.xml` -- a representative, structurally
 * faithful sample of the Central Bank of Azerbaijan's real, live "bank info"
 * XML feed (confirmed live 2026-07-11), including its
 * `<HeadOffices>`/`<BranchOffices>` split and a genuine duplicate-prefix
 * pair (`CTREAZ24`/`CTREAZ22`, both "Dövlət Xəzinədarlığı Agentliyi").
 *
 * @see \Daycry\Iban\Import\Importers\CentralBankOfAzerbaijanImporter
 */
final class CentralBankOfAzerbaijanImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/cbar_sample.xml';

    private CentralBankOfAzerbaijanImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new CentralBankOfAzerbaijanImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('AZ', $this->importer->countryCode());
        self::assertSame('cbar', $this->importer->sourceId());
        self::assertSame('Central Bank of Azerbaijan', $this->importer->sourceName());
        self::assertSame('Central Bank of Azerbaijan', $this->importer->license());
        self::assertStringStartsWith('https://www.cbar.az/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsParsesOnlyHeadOfficeBanksDedupingByFourCharSwiftBicPrefix(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The fixture has 6 <Bank> entries under <HeadOffices>, but the last
        // two (both "Dövlət Xəzinədarlığı Agentliyi") share the 4-char
        // SWIFTBIC prefix 'CTRE', so only 5 unique bank_code rows are
        // yielded. The single <Branch> entry under <BranchOffices> is
        // ignored entirely.
        self::assertCount(5, $rows);

        // The registry's own example bank code (see the IBAN example
        // AZ21NABZ00000000137010001944).
        $cbar = $rows[0];
        self::assertSame('NABZ', $cbar['bank_code']);
        self::assertSame('AR Mərkəzi Bankı', $cbar['name']); // UTF-8 accented char round trip
        self::assertSame('NABZAZ2C', $cbar['bic']);
        self::assertNull($cbar['branch_code']);

        $accessBank = $rows[1];
        self::assertSame('ACAB', $accessBank['bank_code']);
        self::assertSame('ACCESSBANK QSC', $accessBank['name']);
        self::assertSame('ACABAZ22', $accessBank['bic']);

        $ibar = $rows[2];
        self::assertSame('IBAZ', $ibar['bank_code']);
        self::assertSame('Azərbaycan Beynəlxalq Bankı ASC', $ibar['name']);
        self::assertSame('IBAZAZ2X', $ibar['bic']);

        $kapital = $rows[3];
        self::assertSame('AIIB', $kapital['bank_code']);
        self::assertSame('Kapital Bank ASC', $kapital['name']);
        self::assertSame('AIIBAZ2X', $kapital['bic']);

        // The FIRST of the two duplicate-prefix rows wins (SWIFTBIC 'CTREAZ24').
        $treasury = $rows[4];
        self::assertSame('CTRE', $treasury['bank_code']);
        self::assertSame('Dövlət Xəzinədarlığı Agentliyi', $treasury['name']);
        self::assertSame('CTREAZ24', $treasury['bic']);

        $bankCodes = array_column($rows, 'bank_code');
        self::assertSame(['NABZ', 'ACAB', 'IBAZ', 'AIIB', 'CTRE'], $bankCodes);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/cbar.xml'), false);

        self::assertSame([], $rows);
    }
}
