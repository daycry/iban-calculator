<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\SwedenBankInfrastructureImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see SwedenBankInfrastructureImporter} in isolation (plain
 * PHPUnit, framework-free) against a hand-written PSV fixture reproducing the
 * `Bankinfrastruktur/BankData` `source.psv` layout: a `#ClrStart|ClrEnd|
 * IbanId|BIC|BankName` header, several clearing ranges, a duplicate `IbanId`
 * (Nordea = 300, which must dedup to one row), and a range with no `IbanId`
 * (which must be skipped -- clearing columns are never used as the bank
 * code).
 *
 * @see \Daycry\Iban\Import\Importers\SwedenBankInfrastructureImporter
 */
final class SwedenBankInfrastructureImporterTest extends TestCase
{
    private SwedenBankInfrastructureImporter $importer;

    private ?string $fixturePath = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new SwedenBankInfrastructureImporter();
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
        self::assertSame('SE', $this->importer->countryCode());
        self::assertSame('bankinfrastruktur', $this->importer->sourceId());
        self::assertStringContainsString('Bankinfrastruktur', $this->importer->sourceName());
        self::assertStringContainsString('MIT', $this->importer->license());
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
        self::assertStringStartsWith('https://raw.githubusercontent.com/Bankinfrastruktur/', $this->importer->sourceUrl());
    }

    public function testRowsMapIbanIdToBankCodeDedupsAndIgnoresClearingColumns(): void
    {
        $this->fixturePath = $this->writeFixture(
            "#ClrStart|ClrEnd|IbanId|BIC|BankName\n"
            . "5000|5999|500|ESSESESS|Skandinaviska Enskilda Banken\n"
            . "3000|3299|300|NDEASESS|Nordea Bank\n"
            . "3300|3300|300|NDEASESS|Nordea Bank\n"
            . "6000|6999|600|HANDSESS|Svenska Handelsbanken\n"
            . "9550|9569||SBABSESS|SBAB (clearing-only, no IBAN id)\n"
        );

        $rows = iterator_to_array($this->importer->rows($this->fixturePath), false);

        // SEB, Nordea (deduped from two ranges) and Handelsbanken -- the
        // clearing-only, no-IbanId row is skipped.
        self::assertCount(3, $rows);

        $seb = $rows[0];
        self::assertSame('500', $seb['bank_code']); // IbanId, NOT clearing '5000'
        self::assertNull($seb['branch_code']);
        self::assertSame('ESSESESS', $seb['bic']);
        self::assertSame('Skandinaviska Enskilda Banken', $seb['name']);

        $nordea = $rows[1];
        self::assertSame('300', $nordea['bank_code']);
        self::assertSame('Nordea Bank', $nordea['name']);

        $handelsbanken = $rows[2];
        self::assertSame('600', $handelsbanken['bank_code']);
        self::assertSame('HANDSESS', $handelsbanken['bic']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/source.psv'), false);

        self::assertSame([], $rows);
    }

    private function writeFixture(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'iban_se_psv_');
        file_put_contents($path, $contents);

        return $path;
    }
}
