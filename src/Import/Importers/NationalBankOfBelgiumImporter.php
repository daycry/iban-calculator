<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\ReadsXlsxSource;

/**
 * Official-source importer for Belgium (BE): the National Bank of Belgium's
 * (NBB) "Protocol" bank-identification list -- the authoritative registry of
 * every 3-digit bank code (this source's `bank_code`, the first segment of a
 * Belgian IBAN's BBAN) assigned to a Belgian financial institution.
 *
 * - Live source: {@see self::sourceUrl()} -- confirmed live on 2026-07-11 to
 *   be a genuine `.xlsx` (OOXML) spreadsheet, parsed with {@see XlsxReader}
 *   (this package deliberately avoids a full spreadsheet library -- see
 *   `CLAUDE.md`).
 * - Format: one preamble row (a `"Version <date>"` stamp), then a header row
 *   with these EXACT column labels (confirmed live): `T_Identification_Number`
 *   (the 3-digit bank code, WITH leading zeros preserved, e.g. `'000'`,
 *   `'539'`), `Biccode`, `T_Institutions_Dutch`, `T_Institutions_French`,
 *   `T_Institutions_German`, `T_Institutions_English`. Columns are matched by
 *   NAME (via {@see self::locateHeader()}), not position -- the header row
 *   isn't at a fixed index (it follows the version-stamp preamble row).
 * - **Name column fallback**: the live file's `T_Institutions_English` column
 *   is populated for only a handful of institutions -- {@see self::rows()}
 *   therefore falls back EN -> NL -> FR -> DE, taking the first non-empty
 *   one (Dutch is the most consistently populated in practice, but not
 *   universally -- some rows only carry a French name).
 * - **No dedup**: unlike {@see \Daycry\Iban\Import\Importers\CentralBankOfMaltaImporter},
 *   EVERY 3-digit code in this list is its own `bank_code` row -- the live
 *   "full list" (as opposed to NBB's separate grouped/ranged summary file)
 *   enumerates individual codes, not ranges. Defensively, a code cell
 *   containing a `-` (which would indicate a grouped/ranged entry, e.g.
 *   `'000-099'`) fails the digits-only pattern below and is skipped rather
 *   than imported as a bogus `bank_code`.
 * - **Unassigned codes**: some codes are reserved/unassigned (Dutch name
 *   `'VRIJ'` = "free", `Biccode` of `'N/A'`) -- these are imported as
 *   ordinary rows like any other; this importer performs no business-logic
 *   filtering of "is this a real bank" beyond the structural checks
 *   documented here. `bic` still maps `'N/A'`/empty to `null` per
 *   {@see ImporterInterface::rows()}'s contract.
 * - BIC normalization: the live file formats `Biccode` with spaces between
 *   the SWIFT segments (e.g. `'GEBA BE BB'`) -- {@see self::rows()} strips
 *   all internal whitespace so `bic` holds a canonical, space-free BIC (e.g.
 *   `'GEBABEBB'`).
 *
 * CAVEAT: parsing targets the documented/observed source format as of this
 * release -- validate against the live official file before production use;
 * issuing bodies change layouts without notice.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`) plus
 * {@see XlsxReader} (itself framework-free) to fetch/parse, per
 * {@see ImporterInterface}'s framework-free contract -- even though
 * `src/Import/` itself isn't covered by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class NationalBankOfBelgiumImporter implements ImporterInterface
{
    use ReadsXlsxSource;

    private const HEADER_CODE = 'T_Identification_Number';
    private const HEADER_BIC  = 'Biccode';
    private const HEADER_NL   = 'T_Institutions_Dutch';
    private const HEADER_FR   = 'T_Institutions_French';
    private const HEADER_DE   = 'T_Institutions_German';
    private const HEADER_EN   = 'T_Institutions_English';

    private const BANK_CODE_PATTERN = '/^[0-9]{3}$/';

    public function countryCode(): string
    {
        return 'BE';
    }

    public function sourceId(): string
    {
        return 'nbb';
    }

    public function sourceName(): string
    {
        return 'National Bank of Belgium';
    }

    public function license(): string
    {
        return 'National Bank of Belgium';
    }

    public function sourceUrl(): string
    {
        return 'https://www.nbb.be/doc/be/be/protocol/full_list_current.xlsx';
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        $grid = $this->readXlsxGrid($localFile, $this->sourceUrl());

        $header = self::locateHeader($grid);

        if ($header === null) {
            return;
        }

        [$headerRowIndex, $columns] = $header;

        for ($i = $headerRowIndex + 1, $count = count($grid); $i < $count; $i++) {
            $row      = $grid[$i];
            $bankCode = trim($row[$columns[self::HEADER_CODE]] ?? '');

            if (preg_match(self::BANK_CODE_PATTERN, $bankCode) !== 1) {
                continue; // blank row, malformed data, or a grouped/ranged code
            }

            yield [
                'bank_code'   => $bankCode,
                'branch_code' => null,
                'name'        => self::resolveName($row, $columns),
                'bic'         => self::normalizeBic($row[$columns[self::HEADER_BIC]] ?? ''),
            ];
        }
    }

    /**
     * Scans `$grid` for the row carrying every expected header label (see the
     * class docblock), and returns its index plus a label -> column-index
     * map -- or `null` if no such row exists (e.g. an unexpected layout
     * change).
     *
     * @param list<list<string>> $grid
     *
     * @return array{0: int, 1: array<string, int>}|null
     */
    private static function locateHeader(array $grid): ?array
    {
        $expected = [
            self::HEADER_CODE,
            self::HEADER_BIC,
            self::HEADER_NL,
            self::HEADER_FR,
            self::HEADER_DE,
            self::HEADER_EN,
        ];

        foreach ($grid as $rowIndex => $row) {
            $columns = [];

            foreach ($row as $columnIndex => $cell) {
                $trimmed = trim($cell);

                if (in_array($trimmed, $expected, true)) {
                    $columns[$trimmed] = $columnIndex;
                }
            }

            if (count($columns) === count($expected)) {
                return [$rowIndex, $columns];
            }
        }

        return null;
    }

    /**
     * @param list<string>       $row
     * @param array<string, int> $columns
     */
    private static function resolveName(array $row, array $columns): ?string
    {
        foreach ([self::HEADER_EN, self::HEADER_NL, self::HEADER_FR, self::HEADER_DE] as $label) {
            $value = trim($row[$columns[$label]] ?? '');

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Strips internal whitespace from a `Biccode` cell (the live source
     * writes e.g. `'GEBA BE BB'`) and maps an empty/`'N/A'` value to `null`.
     */
    private static function normalizeBic(string $value): ?string
    {
        $normalized = strtoupper(str_replace(' ', '', trim($value)));

        return $normalized !== '' && $normalized !== 'N/A' ? $normalized : null;
    }
}
