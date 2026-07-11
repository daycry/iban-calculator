<?php

declare(strict_types=1);

namespace Tests\Import\Support;

use Daycry\Iban\Import\Support\XlsxReader;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Exercises {@see XlsxReader} against a genuine, minimal `.xlsx` archive
 * built programmatically with {@see ZipArchive} in {@see self::setUp()} --
 * no binary fixture is committed to the repo.
 *
 * The generated workbook deliberately exercises every documented trap:
 * - a shared-string cell (`t="s"`),
 * - an inline-string cell (`t="inlineStr"`),
 * - a plain numeric-literal cell (no `t`, `<v>` only),
 * - a row with an interior GAP (column B absent while A and C are present),
 * - a multi-`<r>`-run shared string (run concatenation), and
 * - a first LOGICAL sheet that is NOT backed by `sheet1.xml` (it's backed
 *   by `sheetA.xml`, resolved via `workbook.xml` + `workbook.xml.rels`),
 *   with a second, unused sheet (`sheetB.xml`) present to prove the reader
 *   doesn't just grab "the first worksheet part it finds".
 *
 * @see \Daycry\Iban\Import\Support\XlsxReader
 */
final class XlsxReaderTest extends TestCase
{
    private const CONTENT_TYPES = <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
            <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
            <Default Extension="xml" ContentType="application/xml"/>
            <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
            <Override PartName="/xl/worksheets/sheetA.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
            <Override PartName="/xl/worksheets/sheetB.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
            <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
        </Types>
        XML;

    private const PACKAGE_RELS = <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
        </Relationships>
        XML;

    private const WORKBOOK = <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
            <sheets>
                <sheet name="First" sheetId="1" r:id="rId1"/>
                <sheet name="Second" sheetId="2" r:id="rId2"/>
            </sheets>
        </workbook>
        XML;

    private const WORKBOOK_RELS = <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
            <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheetA.xml"/>
            <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheetB.xml"/>
        </Relationships>
        XML;

    private const SHARED_STRINGS = <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="3" uniqueCount="3">
            <si><t>Alpha</t></si>
            <si><t>Gamma</t></si>
            <si><r><t>Multi</t></r><r><t>Run</t></r></si>
        </sst>
        XML;

    // First logical sheet's real content. Row 1: shared string (A1),
    // inline string (B1), numeric literal (C1). Row 2: shared string (A2),
    // GAP at B2, numeric literal (C2). Row 3: multi-run shared string (A3).
    private const SHEET_A = <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
            <sheetData>
                <row r="1">
                    <c r="A1" t="s"><v>0</v></c>
                    <c r="B1" t="inlineStr"><is><t>Beta</t></is></c>
                    <c r="C1"><v>42</v></c>
                </row>
                <row r="2">
                    <c r="A2" t="s"><v>1</v></c>
                    <c r="C2"><v>100</v></c>
                </row>
                <row r="3">
                    <c r="A3" t="s"><v>2</v></c>
                </row>
            </sheetData>
        </worksheet>
        XML;

    // Second, UNUSED sheet: deliberately different content, so a reader
    // that ignores the workbook/rels resolution and just grabs some other
    // worksheet part would produce a visibly wrong result.
    private const SHEET_B = <<<'XML'
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
            <sheetData>
                <row r="1">
                    <c r="A1"><v>999</v></c>
                </row>
            </sheetData>
        </worksheet>
        XML;

    private ?string $xlsxPath = null;

    private ?string $notAZipPath = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->xlsxPath !== null && is_file($this->xlsxPath)) {
            unlink($this->xlsxPath);
        }

        if ($this->notAZipPath !== null && is_file($this->notAZipPath)) {
            unlink($this->notAZipPath);
        }

        $this->xlsxPath    = null;
        $this->notAZipPath = null;
    }

    public function testReadFirstSheetResolvesTheFirstLogicalSheetAndHandlesEveryTrap(): void
    {
        $path = $this->buildFixtureXlsx();

        $rows = XlsxReader::readFirstSheet($path);

        self::assertSame([
            ['Alpha', 'Beta', '42'],
            ['Gamma', '', '100'],
            ['MultiRun'],
        ], $rows);
    }

    public function testReadFirstSheetThrowsForNonExistentPath(): void
    {
        $this->expectException(\RuntimeException::class);

        XlsxReader::readFirstSheet(sys_get_temp_dir() . '/xlsx_reader_test_missing_' . uniqid() . '.xlsx');
    }

    public function testReadFirstSheetThrowsForANonZipFile(): void
    {
        $this->notAZipPath = sys_get_temp_dir() . '/xlsx_reader_test_not_a_zip_' . uniqid() . '.xlsx';
        file_put_contents($this->notAZipPath, 'this is definitely not a zip archive');

        $this->expectException(\RuntimeException::class);

        XlsxReader::readFirstSheet($this->notAZipPath);
    }

    private function buildFixtureXlsx(): string
    {
        $path           = sys_get_temp_dir() . '/xlsx_reader_test_' . uniqid() . '.xlsx';
        $this->xlsxPath = $path;

        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        self::assertTrue($opened);

        $zip->addFromString('[Content_Types].xml', self::CONTENT_TYPES);
        $zip->addFromString('_rels/.rels', self::PACKAGE_RELS);
        $zip->addFromString('xl/workbook.xml', self::WORKBOOK);
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::WORKBOOK_RELS);
        $zip->addFromString('xl/sharedStrings.xml', self::SHARED_STRINGS);
        $zip->addFromString('xl/worksheets/sheetA.xml', self::SHEET_A);
        $zip->addFromString('xl/worksheets/sheetB.xml', self::SHEET_B);
        $zip->close();

        return $path;
    }
}
