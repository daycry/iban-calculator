<?php

declare(strict_types=1);

namespace Tests\Import\Support;

use Daycry\Iban\Import\Support\HtmlTableReader;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see HtmlTableReader} against representative, hand-written HTML
 * snippets -- no binary fixture is committed. Covers the documented traps:
 * `<thead>`/`<tbody>` sections, empty cells, `<th>` header cells, a nested
 * table inside a data cell (which must NOT leak its rows into the outer
 * grid), whitespace collapsing, UTF-8 content, and malformed/unclosed markup.
 *
 * @see \Daycry\Iban\Import\Support\HtmlTableReader
 */
final class HtmlTableReaderTest extends TestCase
{
    public function testReadFirstTableReturnsAZeroIndexedGridAcrossTheadAndTbody(): void
    {
        $html = <<<'HTML'
            <table>
                <thead>
                    <tr><th>Bank</th><th>Code</th><th>BIC</th></tr>
                </thead>
                <tbody>
                    <tr><td>Swedbank</td><td>22</td><td>HABAEE2X</td></tr>
                    <tr><td>SEB</td><td>10</td><td>EEUHEE2X</td></tr>
                </tbody>
            </table>
            HTML;

        $grid = HtmlTableReader::readFirstTable($html);

        self::assertSame([
            ['Bank', 'Code', 'BIC'],
            ['Swedbank', '22', 'HABAEE2X'],
            ['SEB', '10', 'EEUHEE2X'],
        ], $grid);
    }

    public function testEmptyCellsBecomeEmptyStringsAndWhitespaceIsCollapsed(): void
    {
        $html = <<<'HTML'
            <table>
                <tr><td>  Central   Bank </td><td></td><td>
                    IOPRVAVX
                </td></tr>
            </table>
            HTML;

        $grid = HtmlTableReader::readFirstTable($html);

        self::assertSame([['Central Bank', '', 'IOPRVAVX']], $grid);
    }

    public function testNestedTableRowsDoNotLeakIntoTheOuterGrid(): void
    {
        // The outer table has ONE data row; its second cell wraps a nested
        // table. The nested table's rows must appear only as their own grid
        // in readTables(), never merged into the outer table's row list, and
        // the wrapping outer cell must not absorb the nested table's text.
        $html = <<<'HTML'
            <table>
                <tr><td>Outer A1</td><td><table><tr><td>Inner</td><td>1</td></tr></table></td></tr>
                <tr><td>Outer A2</td><td>Outer B2</td></tr>
            </table>
            HTML;

        $tables = HtmlTableReader::readTables($html);

        self::assertCount(2, $tables);

        // Outer table: two rows, the nested-table cell reads as empty.
        self::assertSame([
            ['Outer A1', ''],
            ['Outer A2', 'Outer B2'],
        ], $tables[0]);

        // Nested table: its own independent grid.
        self::assertSame([['Inner', '1']], $tables[1]);
    }

    public function testReadTablesReturnsEveryTableInDocumentOrder(): void
    {
        $html = '<table><tr><td>first</td></tr></table>'
            . '<p>text</p>'
            . '<table><tr><td>second</td></tr></table>';

        $tables = HtmlTableReader::readTables($html);

        self::assertSame([
            [['first']],
            [['second']],
        ], $tables);
    }

    public function testReadFirstTableReturnsEmptyArrayWhenThereIsNoTable(): void
    {
        self::assertSame([], HtmlTableReader::readFirstTable('<p>no tables here</p>'));
        self::assertSame([], HtmlTableReader::readFirstTable(''));
    }

    public function testReadFirstTableToleratesMalformedMarkup(): void
    {
        // Unclosed <td>/<tr> tags -- the browser-grade DOM parser recovers.
        $html = '<table><tr><td>200<td>Stopanska<tr><td>300<td>Komercijalna</table>';

        $grid = HtmlTableReader::readFirstTable($html);

        self::assertSame([
            ['200', 'Stopanska'],
            ['300', 'Komercijalna'],
        ], $grid);
    }

    public function testUtf8CellContentIsPreserved(): void
    {
        $html = '<table><tr><td>Zürcher Kantonalbank</td><td>Црногорска</td></tr></table>';

        $grid = HtmlTableReader::readFirstTable($html);

        self::assertSame([['Zürcher Kantonalbank', 'Црногорска']], $grid);
    }

    public function testLocateHeaderFindsTheHeaderRowAndColumnIndicesByName(): void
    {
        $grid = [
            ['Some preamble title spanning the sheet'],
            ['Bank', 'Code', 'BIC'],
            ['Swedbank', '22', 'HABAEE2X'],
        ];

        $located = HtmlTableReader::locateHeader($grid, ['Code', 'BIC']);

        self::assertNotNull($located);
        [$headerRowIndex, $columns] = $located;

        self::assertSame(1, $headerRowIndex);
        self::assertSame(1, $columns['Code']);
        self::assertSame(2, $columns['BIC']);
    }

    public function testLocateHeaderIsCaseInsensitiveAndWhitespaceTolerant(): void
    {
        $grid = [
            ['  bank IDENTIFIERS used in iban ', 'B I C ignored', 'BIC'],
        ];

        $located = HtmlTableReader::locateHeader($grid, ['Bank identifiers used in IBAN', 'BIC']);

        self::assertNotNull($located);
        [$headerRowIndex, $columns] = $located;

        self::assertSame(0, $headerRowIndex);
        self::assertSame(0, $columns['Bank identifiers used in IBAN']);
        self::assertSame(2, $columns['BIC']);
    }

    public function testLocateHeaderReturnsNullWhenNotEveryLabelIsPresent(): void
    {
        $grid = [
            ['Bank', 'Code'],
            ['Swedbank', '22'],
        ];

        self::assertNull(HtmlTableReader::locateHeader($grid, ['Code', 'BIC']));
    }
}
