<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Import\Importers\ReferenceCsvImporter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ReferenceCsvImporterTest extends TestCase
{
    private const HEADER = 'country_code,bank_code,branch_code,bic,name,source_version,source_license';
    private const ROW = 'DK,0001,,TESTDK22,Fixture Bank,fixture-v1,fixture-license';

    public function testReadsAllCountriesAndRetainsLeadingZerosAndRowAttribution(): void
    {
        $importers = ReferenceCsvImporter::fromFile(__DIR__ . '/../../Fixtures/import/reference_sample.csv');
        self::assertSame(['DK', 'FI', 'NL', 'AT', 'FR'], array_map(static fn ($importer) => $importer->countryCode(), $importers));
        $rows = iterator_to_array($importers[0]->rows(), false);
        self::assertSame('0001', $rows[0]['bank_code']);
        self::assertNull($rows[0]['branch_code']);
        self::assertSame('test-dk-2026', $rows[0]['source_version']);
        self::assertSame('fixture-license-dk', $rows[0]['source_license']);
    }

    public function testReorderedColumnsBomAndUnicodeBicAreHandled(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'iban_reference_');
        self::assertNotFalse($path);
        try {
            file_put_contents($path, "\xEF\xBB\xBFname,bic,bank_code,country_code,source_license,branch_code,source_version\n"
                . "Fixture Bank,test\u{00A0}dk22,0001,dk,fixture-license,,fixture-v1\n");
            $importers = ReferenceCsvImporter::fromFile($path);
            self::assertSame('DK', $importers[0]->countryCode());
            $rows = iterator_to_array($importers[0]->rows(), false);
            self::assertSame('TESTDK22', $rows[0]['bic']);
            self::assertSame('0001', $rows[0]['bank_code']);
        } finally {
            unlink($path);
        }
    }

    /** @return iterable<string, array{string}> */
    public static function invalidFileProvider(): iterable
    {
        $prefix = self::HEADER . "\n" . self::ROW . "\n";
        yield 'bad trailing BIC' => [$prefix . 'AT,00001,,NOT-A-BIC,Bank,v1,license'];
        yield 'unknown country' => [$prefix . 'ZZ,00001,,TESTAT22,Bank,v1,license'];
        yield 'empty license' => [$prefix . 'AT,00001,,TESTAT22,Bank,v1,'];
        yield 'invalid bank code' => [$prefix . 'AT,01-00,,TESTAT22,Bank,v1,license'];
        yield 'duplicate natural key' => [$prefix . self::ROW];
        yield 'too many columns' => [$prefix . self::ROW . ',extra'];
        yield 'oversized version' => [$prefix . 'AT,00001,,TESTAT22,Bank,' . str_repeat('a', 65) . ',license'];
        yield 'invalid UTF-8' => [$prefix . "AT,00001,,TESTAT22,Bank\xC2,v1,license"];
        yield 'missing columns' => ["country_code,bank_code\nDK,0001"];
        yield 'duplicate header' => [self::HEADER . ",name\n" . self::ROW . ',Bank'];
        yield 'header only' => [self::HEADER . "\n"];
        yield 'empty file' => [''];
    }

    #[DataProvider('invalidFileProvider')]
    public function testInvalidFilesAreRejectedBeforeAnyRowsAreExposed(string $csv): void
    {
        $path = tempnam(sys_get_temp_dir(), 'iban_reference_');
        self::assertNotFalse($path);
        try {
            file_put_contents($path, $csv);
            $this->expectException(InvalidArgumentException::class);
            ReferenceCsvImporter::fromFile($path);
        } finally {
            unlink($path);
        }
    }

    public function testMissingLocalFileIsRejectedWithoutFetching(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ReferenceCsvImporter::fromFile(__DIR__ . '/does-not-exist.csv');
    }
}
