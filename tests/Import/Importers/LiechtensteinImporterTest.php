<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\LiechtensteinImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see LiechtensteinImporter} in isolation (plain PHPUnit,
 * framework-free) against the SAME hand-crafted fixture
 * `tests/Fixtures/import/six_sample.csv` {@see \Tests\Import\Importers\SixImporterTest}
 * uses -- both importers source SIX Interbank Clearing's Bank Master V3
 * file, filtered to a different `Country` column value each.
 *
 * @see \Daycry\Iban\Import\Importers\LiechtensteinImporter
 * @see \Daycry\Iban\Import\Importers\SixImporter
 */
final class LiechtensteinImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/six_sample.csv';

    private LiechtensteinImporter $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new LiechtensteinImporter();
    }

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, $this->importer);
    }

    public function testMetadataIsAsDocumented(): void
    {
        self::assertSame('LI', $this->importer->countryCode());
        self::assertSame('six', $this->importer->sourceId());
        self::assertSame('SIX Interbank Clearing (Liechtenstein)', $this->importer->sourceName());
        self::assertSame('SIX Interbank Clearing (free use)', $this->importer->license());
        self::assertStringStartsWith('https://api.six-group.com/', $this->importer->sourceUrl());
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen($this->importer->license()));
    }

    public function testRowsYieldsOnlyTheLiechtensteinRowFromTheSharedSixFixture(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        // The shared fixture carries 2 CH rows and a CH merger stub -- none
        // of those must surface here, only the single Country=LI row (IID 8810).
        self::assertCount(1, $rows);

        $llb = $rows[0];
        self::assertSame('08810', $llb['bank_code']); // '8810' left-padded to 5 digits
        self::assertNull($llb['branch_code']);
        self::assertSame('Liechtensteinische Landesbank AG', $llb['name']);
        self::assertSame('Vaduz', $llb['city']);
        self::assertSame('LILALI2XXXX', $llb['bic']);
        self::assertSame('Städtle 44', $llb['address']);
    }

    public function testRowsExcludesSwissRowsFromTheSharedSixFixture(): void
    {
        $rows = iterator_to_array($this->importer->rows(self::FIXTURE), false);

        $bankCodes = array_column($rows, 'bank_code');

        self::assertNotContains('00700', $bankCodes);
        self::assertNotContains('09000', $bankCodes);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array($this->importer->rows('/nonexistent/path/six.csv'), false);

        self::assertSame([], $rows);
    }
}
