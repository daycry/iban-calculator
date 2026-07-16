<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\ReadsCsvSource;

/**
 * Official-source importer for Serbia (RS): the National Bank of Serbia's (NBS)
 * bank directory, which maps each 3-digit bank code (this source's
 * `bank_code`, positions 5-7 of a Serbian IBAN) to its bank name and BIC (e.g.
 * 105 = AIK Banka / AIKBRS22, 160 = Banca Intesa / DBDBRSBG, 170 = UniCredit /
 * BACXRSBG).
 *
 * OFFLINE `--file` ONLY, consuming an OPERATOR-PREPARED CSV. The authoritative
 * data lives in TWO NBS PDFs -- `pregled_racuna_banka.pdf` (code -> BIC, via
 * the settlement-account column `908-Xnn01-kk`) and
 * `pu_jedinstveni_id_brojevi.pdf` (code -> name) -- and this package
 * deliberately ships no PDF reader. Worse, the two PDFs use a two-column layout
 * whose name and code/BIC columns are MISALIGNED, so a naive machine "zip" of
 * the 19 names against the 19 codes is fragile and error-prone. So rather than
 * parse the PDFs directly, this importer consumes a small CSV the operator
 * prepares by hand, cross-checking the two PDFs as they go -- which also lets
 * them drop any defunct/extinct entity. A live, no-`$localFile` call fetches
 * the raw `sourceUrl()` bytes (a PDF) that carry no `code;name;bic` header, so
 * it gracefully yields nothing.
 *
 * PREPARATION RECIPE (operator-side, documented so the `--file` input is
 * reproducible):
 *
 *   1. Extract each PDF's text (e.g. `pdftotext -layout -enc UTF-8 <pdf>`).
 *   2. Read `pu_jedinstveni_id_brojevi.pdf` for code -> name and
 *      `pregled_racuna_banka.pdf` for code -> BIC (the BIC accompanies each
 *      bank's `908-...` settlement account), joining them BY CODE (not by row
 *      order -- that is the misalignment trap).
 *   3. Save a UTF-8 CSV with a `code;name;bic` header (semicolon-delimited),
 *      one row per live bank; leave `bic` blank if unknown.
 *
 * PARSING:
 * - Semicolon-delimited. Columns are located by header NAME
 *   ({@see self::locateHeader()}, case-insensitive substring, accepting either
 *   the English `code`/`name`/`bic` labels or the Serbian `šifra`/`naziv`/`swift`
 *   ones), so their order in the export doesn't matter and a title/preamble
 *   line above the header is ignored.
 * - `bank_code` = the 3-digit code, kept as a STRING and left-padded to 3
 *   digits defensively; a cell that isn't 1-3 digits is skipped.
 * - `bic`: internal whitespace stripped, upper-cased; blank -> `null`.
 *
 * LICENSING: a public regulatory directory with no explicit reuse license;
 * consumed under this package's fetch-on-demand/`--file` posture (the data is
 * not bundled), attributed to the NBS.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`,
 * `mb_*`), per {@see ImporterInterface}'s framework-free contract -- even
 * though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 */
final class NbsSerbiaImporter implements ImporterInterface
{
    use ReadsCsvSource;

    /** @var list<string> Header substrings identifying the code column (case-insensitive). */
    private const HEADER_CODE_TOKENS = ['code', 'šifra', 'sifra'];

    /** @var list<string> Header substrings identifying the name column. */
    private const HEADER_NAME_TOKENS = ['name', 'naziv'];

    /** @var list<string> Header substrings identifying the BIC column. */
    private const HEADER_BIC_TOKENS = ['bic', 'swift'];

    private const CODE_PATTERN = '/^\d{1,3}$/';

    public function countryCode(): string
    {
        return 'RS';
    }

    public function sourceId(): string
    {
        return 'nbs-rs';
    }

    public function sourceName(): string
    {
        return 'National Bank of Serbia (NBS)';
    }

    public function license(): string
    {
        return 'National Bank of Serbia (regulatory directory)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.nbs.rs/documents/platni-sistem/pregled_racuna_banka.pdf';
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        $records = [];

        foreach ($this->csvRecords($localFile, $this->sourceUrl(), ';') as $fields) {
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
     * columns (code / name / BIC), matched by case-insensitive substring, and
     * returns its index plus a `code`/`name`/`bic` column-index map, or `null`
     * if no such row exists.
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
                if ($codeColumn === null && self::cellContains($cell, self::HEADER_CODE_TOKENS)) {
                    $codeColumn = $columnIndex;
                } elseif ($nameColumn === null && self::cellContains($cell, self::HEADER_NAME_TOKENS)) {
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
     * multibyte-aware for the Cyrillic/Latin-Serbian labels).
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
     * Strips internal whitespace from a BIC cell and maps blank to `null`.
     */
    private static function normalizeBic(string $value): ?string
    {
        $normalized = strtoupper((string) preg_replace('/\s+/u', '', $value));

        return $normalized !== '' ? $normalized : null;
    }
}
