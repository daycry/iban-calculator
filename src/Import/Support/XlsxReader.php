<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal, read-only `.xlsx` reader for the official-source importers that
 * ship their data as a spreadsheet rather than a CSV (e.g. BE/FI/HR/HU/LU/
 * MT/NO/GE in v1.2). Deliberately NOT a general spreadsheet library --
 * PhpSpreadsheet is a heavy dependency this package intentionally avoids
 * (see CLAUDE.md).
 *
 * An `.xlsx` file is a ZIP archive of XML parts (OOXML SpreadsheetML).
 * {@see self::readFirstSheet()} extracts ONLY the FIRST worksheet, as a
 * plain 0-indexed grid of cell strings, handling the classic xlsx-parsing
 * traps:
 *
 * - **First-sheet resolution**: the first logical sheet is NOT assumed to
 *   live at `xl/worksheets/sheet1.xml` -- it's resolved via `xl/workbook.xml`'s
 *   first `<sheet>` element's `r:id`, looked up in
 *   `xl/_rels/workbook.xml.rels` to get the real target part. Falls back to
 *   `xl/worksheets/sheet1.xml` only if any step of that resolution fails
 *   (missing part, missing relationship, ...).
 * - **Shared strings**: a cell with `t="s"` holds an INDEX into the
 *   shared-string table (`xl/sharedStrings.xml`'s `<sst><si>` entries), not
 *   literal text. Each `<si>` is either a single `<t>` or a run of `<r><t>`
 *   pieces (rich text broken up by formatting) -- runs are concatenated.
 *   The table may be entirely absent (an all-numeric/all-inline sheet),
 *   which yields an empty table rather than an error.
 * - **Inline strings**: a cell with `t="inlineStr"` holds its text directly
 *   in `<is><t>` (or `<is><r><t>` runs, same concatenation rule as above).
 * - **Numbers/booleans/cached-formula-strings**: a cell with no `t`
 *   attribute (or `t="n"`, `t="b"`, `t="str"`) holds its literal in `<v>`,
 *   returned as-is (e.g. a boolean's `<v>1</v>` becomes `"1"`). Dates are
 *   NEVER converted -- a date-formatted cell is returned as its raw serial
 *   number, since number formats/styles are not read at all.
 * - **Column placement by cell reference**: each `<c r="B3">` carries its
 *   column letter(s) (`A`, `B`, ..., `Z`, `AA`, ...), converted to a 0-based
 *   index (A=0, B=1, ..., Z=25, AA=26, ...) and used to PLACE the value, so
 *   an omitted/empty interior cell doesn't shift later columns -- gaps
 *   become `''`.
 *
 * Row shape: each row is a `list<string>` sized to its OWN last populated
 * cell -- interior gaps within a row become `''`, but rows are NOT padded
 * to the width of the widest row in the sheet. A `<row>` with no `<c>`
 * children at all yields an empty list `[]` (there is no cell reference to
 * size it against). Rows are returned in document order.
 *
 * Deliberately unsupported (by design, this is a reader, not a spreadsheet
 * engine): formula evaluation (only a cached `t="str"` result is read),
 * styles/number formats, merged cells, multiple worksheets, legacy `.xls`.
 *
 * FRAMEWORK-FREE: uses only native PHP (`ZipArchive`, `SimpleXMLElement`),
 * the same convention the importers in `src/Import/Importers/` follow --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`. Requires `ext-zip`
 * (declared in `composer.json`); `ext-simplexml` ships with PHP by default.
 *
 * @see \Daycry\Iban\Contracts\ImporterInterface
 */
final class XlsxReader
{
    private const RELATIONSHIPS_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const FALLBACK_SHEET_PATH = 'xl/worksheets/sheet1.xml';

    /**
     * Reads the FIRST worksheet of the `.xlsx` file at `$path` as a plain
     * grid of cell strings.
     *
     * @return list<list<string>> rows of the first worksheet, in document
     *   order; each row is a 0-indexed list of cell strings placed by
     *   column reference (see the class docblock for the exact gap/shape
     *   rules)
     *
     * @throws RuntimeException if `$path` cannot be opened as a zip
     *   archive, or the resolved worksheet part is missing/unparseable
     */
    public static function readFirstSheet(string $path): array
    {
        $zip         = new ZipArchive();
        $openResult  = $zip->open($path);

        if ($openResult !== true) {
            throw new RuntimeException(sprintf(
                'XlsxReader: unable to open "%s" as a zip archive (code %s).',
                $path,
                $openResult,
            ));
        }

        try {
            $sharedStrings = self::readSharedStrings($zip);
            $sheetPath     = self::resolveFirstSheetPath($zip);
            $sheetXml      = self::readXmlPart($zip, $sheetPath);

            if ($sheetXml === null) {
                throw new RuntimeException(sprintf(
                    'XlsxReader: worksheet part "%s" is missing or not valid XML in "%s".',
                    $sheetPath,
                    $path,
                ));
            }

            return self::parseSheet($sheetXml, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    /**
     * Loads `xl/sharedStrings.xml`'s `<si>` entries into a flat, index-
     * addressable table. Absent entirely (no shared strings used anywhere
     * in the workbook) yields an empty table rather than an error.
     *
     * @return list<string>
     */
    private static function readSharedStrings(ZipArchive $zip): array
    {
        $xml = self::readXmlPart($zip, 'xl/sharedStrings.xml');

        if ($xml === null) {
            return [];
        }

        $strings = [];

        foreach ($xml->si as $si) {
            $strings[] = self::extractRichText($si);
        }

        return $strings;
    }

    /**
     * Concatenates the text of a rich-text node (`<si>` or `<is>`): either
     * a single direct `<t>` child, or the `<t>` of every `<r>` (run) child
     * in order.
     */
    private static function extractRichText(SimpleXMLElement $node): string
    {
        if (isset($node->r)) {
            $text = '';

            foreach ($node->r as $run) {
                $text .= (string) $run->t;
            }

            return $text;
        }

        return (string) $node->t;
    }

    /**
     * Resolves the real part path of the workbook's FIRST logical sheet via
     * `xl/workbook.xml`'s first `<sheet>` element's `r:id`, looked up in
     * `xl/_rels/workbook.xml.rels`. Falls back to
     * {@see self::FALLBACK_SHEET_PATH} if any step fails.
     */
    private static function resolveFirstSheetPath(ZipArchive $zip): string
    {
        $workbook = self::readXmlPart($zip, 'xl/workbook.xml');

        if ($workbook === null || ! isset($workbook->sheets->sheet[0])) {
            return self::FALLBACK_SHEET_PATH;
        }

        $firstSheet = $workbook->sheets->sheet[0];
        $attributes = $firstSheet->attributes(self::RELATIONSHIPS_NS);
        $relationshipId = $attributes !== null ? (string) $attributes->id : '';

        if ($relationshipId === '') {
            return self::FALLBACK_SHEET_PATH;
        }

        $rels = self::readXmlPart($zip, 'xl/_rels/workbook.xml.rels');

        if ($rels === null) {
            return self::FALLBACK_SHEET_PATH;
        }

        foreach ($rels->Relationship as $relationship) {
            if ((string) $relationship['Id'] !== $relationshipId) {
                continue;
            }

            $target = (string) $relationship['Target'];

            if ($target === '') {
                return self::FALLBACK_SHEET_PATH;
            }

            return self::resolveTargetPath($target);
        }

        return self::FALLBACK_SHEET_PATH;
    }

    /**
     * Resolves a workbook-relationship `Target` -- relative to `xl/` (e.g.
     * `worksheets/sheet2.xml`), or package-absolute with a leading `/`
     * (e.g. `/xl/worksheets/sheet2.xml`) -- into a path inside the zip.
     */
    private static function resolveTargetPath(string $target): string
    {
        $target = ltrim($target, '/');

        return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
    }

    /**
     * Reads a zip entry and parses it as XML, returning `null` if the entry
     * is absent/empty or isn't valid XML (never throws for a missing part
     * on its own -- callers decide whether that's fatal).
     */
    private static function readXmlPart(ZipArchive $zip, string $entryName): ?SimpleXMLElement
    {
        $contents = $zip->getFromName($entryName);

        if ($contents === false || $contents === '') {
            return null;
        }

        $xml = @simplexml_load_string($contents);

        return $xml instanceof SimpleXMLElement ? $xml : null;
    }

    /**
     * @param list<string> $sharedStrings
     *
     * @return list<list<string>>
     */
    private static function parseSheet(SimpleXMLElement $sheetXml, array $sharedStrings): array
    {
        $rows = [];

        foreach ($sheetXml->sheetData->row as $rowXml) {
            $rows[] = self::parseRow($rowXml, $sharedStrings);
        }

        return $rows;
    }

    /**
     * @param list<string> $sharedStrings
     *
     * @return list<string>
     */
    private static function parseRow(SimpleXMLElement $rowXml, array $sharedStrings): array
    {
        /** @var array<int, string> $cells */
        $cells = [];

        foreach ($rowXml->c as $cellXml) {
            $reference = (string) $cellXml['r'];
            $index     = $reference !== '' ? self::columnIndex($reference) : count($cells);

            $cells[$index] = self::cellValue($cellXml, $sharedStrings);
        }

        if ($cells === []) {
            return [];
        }

        $lastIndex = max(array_keys($cells));
        $row       = [];

        for ($i = 0; $i <= $lastIndex; $i++) {
            $row[] = $cells[$i] ?? '';
        }

        return $row;
    }

    /**
     * Converts a cell reference's column-letter part (e.g. `"B3"` -> `"B"`,
     * `"AA12"` -> `"AA"`) into a 0-based column index (A=0, B=1, ..., Z=25,
     * AA=26, ...).
     */
    private static function columnIndex(string $cellReference): int
    {
        if (preg_match('/^([A-Za-z]+)/', $cellReference, $matches) !== 1) {
            return 0;
        }

        $letters = strtoupper($matches[1]);
        $index   = 0;

        for ($i = 0, $length = strlen($letters); $i < $length; $i++) {
            $index = $index * 26 + (ord($letters[$i]) - ord('A') + 1);
        }

        return $index - 1;
    }

    /**
     * Resolves a single `<c>` cell element to its string value, handling
     * shared strings (`t="s"`), inline strings (`t="inlineStr"`), and
     * numbers/booleans/cached-formula-strings (`t=""`/`"n"`/`"b"`/`"str"`,
     * literal in `<v>`).
     *
     * @param list<string> $sharedStrings
     */
    private static function cellValue(SimpleXMLElement $cellXml, array $sharedStrings): string
    {
        $type = (string) $cellXml['t'];

        if ($type === 'inlineStr') {
            return isset($cellXml->is) ? self::extractRichText($cellXml->is) : '';
        }

        if ($type === 's') {
            $sharedIndex = (string) $cellXml->v;

            if ($sharedIndex === '') {
                return '';
            }

            return $sharedStrings[(int) $sharedIndex] ?? '';
        }

        // 'b' (boolean), 'str' (cached formula string), 'n'/'' (number):
        // all hold their literal directly in <v> as plain text.
        return isset($cellXml->v) ? (string) $cellXml->v : '';
    }
}
