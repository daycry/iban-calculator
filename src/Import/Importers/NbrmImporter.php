<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\ReadsCsvSource;

/**
 * Official-source importer for North Macedonia (MK): the NBRM's (National Bank
 * of the Republic of North Macedonia) "Листа на доделени водечки броеви на
 * банките" (list of assigned bank leading numbers), which maps each 3-digit
 * "Водечки број" (leading number -- this source's `bank_code`, the first 3
 * digits of a Macedonian BBAN) to its bank name and SWIFT BIC (e.g. 200 =
 * Stopanska banka, 210 = NLB Banka, 300 = Komercijalna banka).
 *
 * OFFLINE `--file` ONLY. The whole `nbrm.mk` site sits behind a Cloudflare
 * challenge that 403s every automated fetch, and the authoritative file is a
 * legacy `.xls` (BIFF, cp1251) or `.docx` -- neither readable by this
 * package's {@see \Daycry\Iban\Import\Support\XlsxReader} (OOXML only), and
 * this package ships no BIFF/DOCX reader. So a live, no-`$localFile` call
 * cannot fetch/parse it and gracefully yields nothing. The supported workflow
 * is `iban:update --source=nbrm --country=MK --file=<csv>` with a CSV the
 * operator exported from the `.xls`/`.docx` themselves.
 *
 * EXPORT RECIPE (operator-side): open the `.xls`/`.docx` roster and "Save As"
 * a **comma-delimited, UTF-8 CSV** keeping the header row (`Р.бр | SWIFT BIC |
 * Назив на банка | Водечки број`). If the export instead keeps the legacy
 * cp1251 codepage, {@see self::decodeCsvBytes()} still decodes the Cyrillic
 * names by converting from Windows-1251 when the bytes aren't valid UTF-8.
 *
 * PARSING:
 * - Columns are located by header NAME ({@see self::locateHeader()}, Cyrillic
 *   substring match), so their order in the export doesn't matter and the
 *   `Р.бр` row-number column is never mistaken for the bank code. The header
 *   row is the first row carrying all three of a "Водечки"/"Назив"/"SWIFT|BIC"
 *   column; a title/preamble line above it is ignored.
 * - `bank_code` = the 3-digit leading number, kept as a STRING and left-padded
 *   to 3 digits defensively; a cell that isn't 1-3 digits is skipped.
 * - `bic`: internal whitespace stripped, upper-cased; blank -> `null`.
 *
 * FRESHNESS CAVEAT: the only publicly confirmable edition of this roster is
 * v1.15 (2014); it may predate mergers/liquidations (e.g. Eurostandard banka,
 * leading number 370, was liquidated in 2020). VERIFY the roster on download
 * and drop any defunct institutions before importing -- do not assume every
 * historical leading number is still live.
 *
 * LICENSING: a public regulatory roster with no explicit reuse license;
 * consumed under this package's fetch-on-demand/`--file` posture (the data is
 * not bundled), attributed to the NBRM.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`,
 * `mb_*`, `iconv()`), per {@see ImporterInterface}'s framework-free contract
 * -- even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`. Requires `ext-mbstring`
 * and `ext-iconv` (declared in `composer.json`).
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 */
final class NbrmImporter implements ImporterInterface
{
    use ReadsCsvSource;

    /** Cyrillic/Latin header substrings identifying each column (case-insensitive). */
    private const HEADER_CODE = 'Водеч';
    private const HEADER_NAME = 'Назив';

    /** @var list<string> Either token marks the BIC column. */
    private const HEADER_BIC_TOKENS = ['SWIFT', 'BIC'];

    private const CODE_PATTERN = '/^\d{1,3}$/';

    public function countryCode(): string
    {
        return 'MK';
    }

    public function sourceId(): string
    {
        return 'nbrm';
    }

    public function sourceName(): string
    {
        return 'NBRM (National Bank of North Macedonia)';
    }

    public function license(): string
    {
        return 'NBRM (regulatory roster)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.nbrm.mk/platni_sistemi.nspx';
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        $records = [];

        foreach ($this->csvRecords($localFile, $this->sourceUrl(), ',') as $fields) {
            if ($fields === [null]) {
                continue; // blank line
            }

            $records[] = array_values(array_map(static fn ($v): string => (string) $v, $fields));
        }

        $header = self::locateHeader($records);

        if ($header === null) {
            return;
        }

        [$headerRowIndex, $columns] = $header;

        for ($i = $headerRowIndex + 1, $count = count($records); $i < $count; $i++) {
            $row  = $records[$i];
            $code = trim($row[$columns['code']] ?? '');

            if (preg_match(self::CODE_PATTERN, $code) !== 1) {
                continue; // not a data row
            }

            yield [
                'bank_code'   => str_pad($code, 3, '0', STR_PAD_LEFT),
                'branch_code' => null,
                'name'        => self::nullableTrim($row[$columns['name']] ?? ''),
                'bic'         => self::normalizeBic($row[$columns['bic']] ?? ''),
            ];
        }
    }

    /**
     * Scans `$records` for the first row carrying all three expected header
     * columns (code / name / BIC), matched by case-insensitive Cyrillic/Latin
     * substring, and returns its index plus a `code`/`name`/`bic` column-index
     * map, or `null` if no such row exists.
     *
     * @param list<list<string>> $records
     *
     * @return array{0: int, 1: array{code: int, name: int, bic: int}}|null
     */
    private static function locateHeader(array $records): ?array
    {
        foreach ($records as $rowIndex => $row) {
            $codeColumn = null;
            $nameColumn = null;
            $bicColumn  = null;

            foreach ($row as $columnIndex => $cell) {
                if ($codeColumn === null && self::cellContains($cell, [self::HEADER_CODE])) {
                    $codeColumn = $columnIndex;
                } elseif ($nameColumn === null && self::cellContains($cell, [self::HEADER_NAME])) {
                    $nameColumn = $columnIndex;
                } elseif ($bicColumn === null && self::cellContains($cell, self::HEADER_BIC_TOKENS)) {
                    $bicColumn = $columnIndex;
                }
            }

            if ($codeColumn !== null && $nameColumn !== null && $bicColumn !== null) {
                return [$rowIndex, ['code' => $codeColumn, 'name' => $nameColumn, 'bic' => $bicColumn]];
            }
        }

        return null;
    }

    /**
     * `true` when `$cell` contains any of `$needles` (case-insensitive,
     * multibyte-aware for the Cyrillic labels).
     *
     * @param list<string> $needles
     */
    private static function cellContains(string $cell, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (mb_stripos($cell, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Overrides {@see ReadsCsvSource::decodeCsvBytes()}'s default: this
     * source's fallback codepage is a FIXED Windows-1251 (Cyrillic), not the
     * trait default's Windows-1252 guess -- the authoritative `.xls` is cp1251.
     * Uses `iconv()` (system libiconv ships `Windows-1251` reliably); if it
     * fails, the raw bytes are returned unconverted rather than throwing.
     */
    protected function decodeCsvBytes(string $raw): string
    {
        $raw = self::stripBom($raw);

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = @iconv('Windows-1251', 'UTF-8', $raw);
            $raw       = $converted !== false ? $converted : $raw;
        }

        return $raw;
    }

    /**
     * Strips internal whitespace from a BIC cell and maps blank to `null`.
     */
    private static function normalizeBic(string $value): ?string
    {
        $normalized = strtoupper((string) preg_replace('/\s+/u', '', $value));

        return $normalized !== '' ? $normalized : null;
    }
}
