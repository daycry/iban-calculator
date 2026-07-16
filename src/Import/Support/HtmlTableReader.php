<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Minimal, read-only HTML `<table>` reader for the official-source importers
 * whose national bank directory is published only as an HTML page rather than
 * a CSV/XLSX/JSON download (e.g. EE/ME/IT and the CY landing page in the v2.x
 * SEPA-coverage batch). The companion to {@see XlsxReader}: same shape (a
 * plain 0-indexed grid of cell strings) and same design posture -- this is a
 * table extractor, NOT a general HTML/DOM toolkit.
 *
 * {@see self::readTables()} returns EVERY `<table>` in the document, in
 * document order, each as a `list<list<string>>` grid; {@see self::readFirstTable()}
 * returns just the first (or `[]` when the document has no table). Cell text
 * is the concatenation of the cell's own descendant text, with runs of
 * whitespace (including non-breaking spaces and newlines) collapsed to a
 * single space and the result trimmed -- so a cell laid out across several
 * indented source lines reads back as one clean value.
 *
 * The classic table-scraping traps this handles:
 *
 * - **`<thead>`/`<tbody>`/`<tfoot>` sections**: rows are collected from
 *   wherever they sit inside the table, in document order, so a header row in
 *   `<thead>` and body rows in `<tbody>` end up as consecutive grid rows with
 *   no section boundary of their own.
 * - **`<th>` and `<td>` alike**: header cells and data cells are both read;
 *   nothing is dropped just for being a `<th>`.
 * - **Nested tables**: a `<table>` inside a data cell is NOT merged into the
 *   outer table -- its rows never leak into the outer grid, and the wrapping
 *   outer cell reads as its own direct text only (typically empty). The
 *   nested table instead surfaces as its own separate grid in
 *   {@see self::readTables()} (after its container, per document order). This
 *   is why row/cell collection is scoped by nearest-ancestor table rather
 *   than a blunt `getElementsByTagName()` sweep.
 * - **Empty cells**: an empty `<td></td>` becomes `''` (an interior gap is
 *   preserved positionally, exactly like {@see XlsxReader}), never skipped.
 * - **Malformed markup**: unclosed `<tr>`/`<td>` tags and other real-world
 *   sloppiness are recovered by libxml's browser-grade HTML parser, with its
 *   warnings suppressed (`libxml_use_internal_errors()`).
 * - **UTF-8**: multibyte content (accented bank names, Cyrillic, ...) is
 *   preserved by pre-encoding non-ASCII code points as numeric entities
 *   before handing the bytes to `DOMDocument::loadHTML()` (which otherwise
 *   assumes ISO-8859-1), so cell text comes back as clean UTF-8.
 *
 * Deliberately unsupported, by design: `colspan`/`rowspan` (not observed in
 * the target sources -- a spanning cell occupies a single grid position),
 * CSS/`display`, and any notion of "the visually rendered" table.
 *
 * {@see self::locateHeader()} is the shared helper each importer uses to find
 * its header row and map its expected column labels to 0-based indices
 * (case-insensitive, whitespace-normalized), mirroring the private
 * `locateHeader()` the XLSX importers carry -- so an importer matches its
 * columns by NAME rather than a brittle fixed position.
 *
 * FRAMEWORK-FREE: uses only native PHP (`DOMDocument`, `mb_encode_numericentity()`),
 * the same convention the importers in `src/Import/Importers/` follow.
 * Requires `ext-dom` (declared in `composer.json`); `ext-mbstring` is already
 * a package requirement.
 *
 * CAVEAT: HTML scraping is structurally fragile -- a source site's redesign
 * can change or remove the table without notice. Each importer built on this
 * reader documents that caveat and validates against the live page before
 * production use, exactly like the rest of the catalog.
 *
 * @see \Daycry\Iban\Import\Support\XlsxReader
 * @see \Daycry\Iban\Contracts\ImporterInterface
 */
final class HtmlTableReader
{
    /**
     * Reads the FIRST `<table>` in `$html` as a plain grid of cell strings,
     * or `[]` if the document contains no table.
     *
     * @return list<list<string>>
     */
    public static function readFirstTable(string $html): array
    {
        $tables = self::readTables($html);

        return $tables[0] ?? [];
    }

    /**
     * Reads EVERY `<table>` in `$html`, in document order, each as its own
     * 0-indexed grid of cell strings.
     *
     * @return list<list<list<string>>>
     */
    public static function readTables(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $doc      = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        // DOMDocument::loadHTML() assumes ISO-8859-1 for byte input with no
        // encoding declaration; pre-encoding non-ASCII code points as numeric
        // entities makes the UTF-8 content survive the round trip.
        $prepared = mb_encode_numericentity($html, [0x80, 0x10FFFF, 0, 0x1FFFFF], 'UTF-8');

        $doc->loadHTML($prepared, LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $tables = [];

        foreach ($doc->getElementsByTagName('table') as $table) {
            $tables[] = self::readTable($table);
        }

        return $tables;
    }

    /**
     * Scans `$grid` for the first row that contains EVERY label in
     * `$expectedLabels` (matched case-insensitively after collapsing
     * whitespace), and returns that row's index together with a
     * label -> 0-based-column-index map -- or `null` if no single row carries
     * all of them.
     *
     * The returned map is keyed by the ORIGINAL label strings passed in (not
     * their normalized form), so callers can index it with the same constants
     * they queried with.
     *
     * @param list<list<string>> $grid
     * @param list<string>       $expectedLabels
     *
     * @return array{0: int, 1: array<string, int>}|null
     */
    public static function locateHeader(array $grid, array $expectedLabels): ?array
    {
        foreach ($grid as $rowIndex => $row) {
            /** @var array<string, int> $byNormalized normalized cell text -> first column index */
            $byNormalized = [];

            foreach ($row as $columnIndex => $cell) {
                $normalized = self::normalizeLabel($cell);

                $byNormalized[$normalized] ??= $columnIndex;
            }

            $columns = [];

            foreach ($expectedLabels as $label) {
                $normalized = self::normalizeLabel($label);

                if (! isset($byNormalized[$normalized])) {
                    continue 2; // this row is missing a required label
                }

                $columns[$label] = $byNormalized[$normalized];
            }

            return [$rowIndex, $columns];
        }

        return null;
    }

    /**
     * Reads one `<table>` element into a grid, scoping rows/cells to this
     * table so a nested table's contents never leak in.
     *
     * @return list<list<string>>
     */
    private static function readTable(DOMElement $table): array
    {
        $grid = [];

        foreach ($table->getElementsByTagName('tr') as $tr) {
            if (self::nearestTable($tr) !== $table) {
                continue; // a row belonging to a nested table
            }

            $grid[] = self::readRow($tr);
        }

        return $grid;
    }

    /**
     * Reads one `<tr>`'s direct `<td>`/`<th>` children into a list of cell
     * strings (a nested table's own cells sit under that table's rows, not
     * this one, so iterating direct children is enough to keep them out).
     *
     * @return list<string>
     */
    private static function readRow(DOMElement $tr): array
    {
        $cells = [];

        foreach ($tr->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $tag = strtolower($child->tagName);

            if ($tag === 'td' || $tag === 'th') {
                $cells[] = self::cellText($child);
            }
        }

        return $cells;
    }

    /**
     * Extracts a cell's own text: concatenates descendant text, skipping any
     * nested `<table>` subtree, then collapses whitespace (including
     * non-breaking spaces) to single spaces and trims.
     */
    private static function cellText(DOMElement $cell): string
    {
        $text = self::collectText($cell);
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Recursively concatenates the text of `$node`'s descendants, skipping
     * any nested `<table>` element (so a table-in-a-cell contributes nothing
     * to the wrapping cell's text).
     */
    private static function collectText(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement) {
                if (strtolower($child->tagName) === 'table') {
                    continue;
                }

                $text .= self::collectText($child);

                continue;
            }

            $text .= $child->textContent;
        }

        return $text;
    }

    /**
     * Returns the nearest ancestor `<table>` element of `$node`, or `null`.
     */
    private static function nearestTable(DOMNode $node): ?DOMElement
    {
        $parent = $node->parentNode;

        while ($parent !== null) {
            if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'table') {
                return $parent;
            }

            $parent = $parent->parentNode;
        }

        return null;
    }

    /**
     * Normalizes a label/cell for header matching: non-breaking spaces to
     * spaces, whitespace runs collapsed, trimmed, lower-cased.
     */
    private static function normalizeLabel(string $value): string
    {
        $value = str_replace("\u{00A0}", ' ', $value);
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return mb_strtolower($value);
    }
}
