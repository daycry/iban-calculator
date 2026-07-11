<?php

declare(strict_types=1);

namespace Tests\_support;

use ZipArchive;

/**
 * Builds a minimal, valid `.xlsx` file from a plain `list<list<string>>` grid
 * -- for tests that exercise an XLSX-based importer (see
 * `src/Import/Support/XlsxReader.php`) without committing a binary fixture to
 * the repo. Reuses the `ZipArchive` + minimal-OOXML-parts technique proven in
 * `tests/Import/Support/XlsxReaderTest.php`, generalized to take an arbitrary
 * grid instead of a fixed, hand-written one.
 *
 * Every cell is written as an INLINE STRING (`t="inlineStr"`), never a shared
 * string -- far simpler to generate correctly than a `sharedStrings.xml`
 * table, and {@see \Daycry\Iban\Import\Support\XlsxReader} reads both kinds
 * identically. A row's cells always start at column A and are written
 * contiguously (no interior gaps), which is all a fixture needs -- gap
 * handling itself is already covered by `XlsxReaderTest`.
 *
 * Introduced in v1.2's BE/HR/LU/MT batch so every XLSX-sourced importer test
 * (this batch's, and the FI/HU/NO/GE batch that follows it) shares one
 * fixture-generation helper instead of each duplicating the `ZipArchive`
 * wiring.
 *
 * @see \Daycry\Iban\Import\Support\XlsxReader
 */
final class XlsxFixtureFactory
{
    /**
     * Writes `$rows` as the first (and only) worksheet of a fresh `.xlsx`
     * file, and returns the path written to.
     *
     * @param list<list<string>> $rows Rows to write, in order; each row's
     *                                    cells are placed starting at column A.
     * @param string|null        $path Destination path; a fresh temp file is
     *                                    created when omitted.
     */
    public static function write(array $rows, ?string $path = null): string
    {
        $path ??= (string) tempnam(sys_get_temp_dir(), 'iban_xlsx_fixture_');

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypes());
        $zip->addFromString('_rels/.rels', self::packageRels());
        $zip->addFromString('xl/workbook.xml', self::workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRels());
        $zip->addFromString('xl/worksheets/sheet1.xml', self::sheet($rows));
        $zip->close();

        return $path;
    }

    private static function contentTypes(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
                <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
                <Default Extension="xml" ContentType="application/xml"/>
                <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
                <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
            </Types>
            XML;
    }

    private static function packageRels(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
            </Relationships>
            XML;
    }

    private static function workbook(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
                <sheets>
                    <sheet name="Sheet1" sheetId="1" r:id="rId1"/>
                </sheets>
            </workbook>
            XML;
    }

    private static function workbookRels(): string
    {
        return <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
                <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
            </Relationships>
            XML;
    }

    /**
     * @param list<list<string>> $rows
     */
    private static function sheet(array $rows): string
    {
        $xmlRows = '';

        foreach ($rows as $rowIndex => $row) {
            $rowNumber = $rowIndex + 1;
            $cells     = '';

            foreach ($row as $columnIndex => $value) {
                $reference = self::columnLetter($columnIndex) . $rowNumber;
                $escaped   = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $cells .= sprintf('<c r="%s" t="inlineStr"><is><t>%s</t></is></c>', $reference, $escaped);
            }

            $xmlRows .= sprintf('<row r="%d">%s</row>', $rowNumber, $cells);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            . $xmlRows
            . '</sheetData></worksheet>';
    }

    /**
     * Converts a 0-based column index into its spreadsheet letter(s)
     * (0 -> `A`, 1 -> `B`, ..., 25 -> `Z`, 26 -> `AA`, ...) -- the inverse of
     * {@see \Daycry\Iban\Import\Support\XlsxReader}'s column-index resolution.
     */
    private static function columnLetter(int $index): string
    {
        $letter = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letter = chr((($index - 1) % 26) + 65) . $letter;
        }

        return $letter;
    }
}
