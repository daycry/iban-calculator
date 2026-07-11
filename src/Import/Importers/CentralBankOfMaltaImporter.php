<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\ReadsXlsxSource;

/**
 * Official-source importer for Malta (MT): the Central Bank of Malta's "BIC
 * sort codes" list -- the authoritative registry of every institution's BIC
 * and per-branch "National ID (Sort Code)", from which this importer derives
 * a bank-level `bank_code` (this source's `bank_code`, the segment a Maltese
 * IBAN's BBAN starts with).
 *
 * - Live source: {@see self::sourceUrl()} -- confirmed live on 2026-07-11 to
 *   be a genuine `.xlsx` (OOXML) spreadsheet, parsed with {@see XlsxReader}
 *   (this package deliberately avoids a full spreadsheet library -- see
 *   `CLAUDE.md`).
 * - Format: no preamble -- the header row is row 1, with these EXACT column
 *   labels (confirmed live): `BIC Code`, `Financial Institution Name`,
 *   `National ID (Sort Code)`, `Branch`, `Remarks`. Only `BIC Code` and
 *   `Financial Institution Name` are used here -- `National ID (Sort Code)`,
 *   `Branch` and `Remarks` describe per-branch detail this importer's
 *   bank-level rows don't carry. Columns are matched by NAME (via
 *   {@see self::locateHeader()}), not position.
 * - **`bank_code` derivation, NOT the sort code**: this source is published
 *   PER SORT CODE (one row per physical branch/service), with the same
 *   institution's `BIC Code` repeating across several rows (e.g. Lombard
 *   Bank Malta plc.'s head office row carries the full BIC, followed by
 *   several branch rows with an EMPTY `BIC Code`/name -- i.e. naturally
 *   excluded below since {@see self::rows()} requires a non-empty BIC to
 *   derive a `bank_code` at all). `bank_code` is therefore the FIRST 4
 *   CHARACTERS of `BIC Code` (e.g. `'VALLMTMT'` -> `'VALL'`), which is the
 *   segment a Maltese IBAN's BBAN actually starts with -- NOT the 5-digit
 *   sort code, which never appears in the IBAN.
 * - **Dedup by the 4-char `bank_code`**: several DIFFERENT full BIC codes
 *   share the same 4-char prefix across multiple rows (e.g. `'PYMXMTMTXXX'`
 *   and `'PYMXMTMTMAL'` both start with `'PYMX'`, one row per sort code of
 *   the same institution) -- {@see self::rows()} yields exactly ONE row per
 *   unique 4-char `bank_code`, keeping the FIRST occurrence's `name`/`bic`
 *   and silently skipping every later row sharing that prefix, since the
 *   `banks` table is keyed on `(country_code, bank_code, branch_code)` and a
 *   second insert would just be a no-op duplicate at best.
 * - No BIC/name normalization beyond trimming and uppercasing the BIC: this
 *   source's `BIC Code` values are already space-free (unlike
 *   {@see \Daycry\Iban\Import\Importers\NationalBankOfBelgiumImporter}/
 *   {@see \Daycry\Iban\Import\Importers\LuxembourgBankersAssociationImporter}'s
 *   sources), but are defensively uppercased/trimmed for consistency.
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
final class CentralBankOfMaltaImporter implements ImporterInterface
{
    use ReadsXlsxSource;

    private const BANK_CODE_LENGTH = 4;

    public function countryCode(): string
    {
        return 'MT';
    }

    public function sourceId(): string
    {
        return 'cbm';
    }

    public function sourceName(): string
    {
        return 'Central Bank of Malta';
    }

    public function license(): string
    {
        return 'Central Bank of Malta';
    }

    public function sourceUrl(): string
    {
        return 'https://www.centralbankmalta.org/site/Payment-Systems/BIC-sort-codes.xlsx';
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

        /** @var array<string, true> $seen */
        $seen = [];

        for ($i = $headerRowIndex + 1, $count = count($grid); $i < $count; $i++) {
            $row = $grid[$i];
            $bic = strtoupper(trim($row[$columns['bic']] ?? ''));

            if (strlen($bic) < self::BANK_CODE_LENGTH) {
                continue; // continuation/branch row with no BIC of its own, or malformed data
            }

            $bankCode = substr($bic, 0, self::BANK_CODE_LENGTH);

            if (isset($seen[$bankCode])) {
                continue; // same institution, a different sort code already seen
            }

            $seen[$bankCode] = true;

            yield [
                'bank_code'   => $bankCode,
                'branch_code' => null,
                'name'        => self::nullableTrim($row[$columns['name']] ?? ''),
                'bic'         => $bic,
            ];
        }
    }

    /**
     * Scans `$grid` for the row carrying both expected header cells --
     * `"BIC Code"` and `"Financial Institution Name"` (compared
     * case-insensitively after trimming) -- and returns its index plus a
     * `bic`/`name` column-index map, or `null` if no such row exists (e.g.
     * an unexpected layout change).
     *
     * @param list<list<string>> $grid
     *
     * @return array{0: int, 1: array{bic: int, name: int}}|null
     */
    private static function locateHeader(array $grid): ?array
    {
        foreach ($grid as $rowIndex => $row) {
            $bicColumn  = null;
            $nameColumn = null;

            foreach ($row as $columnIndex => $cell) {
                $trimmed = trim($cell);

                if ($bicColumn === null && strcasecmp($trimmed, 'BIC Code') === 0) {
                    $bicColumn = $columnIndex;
                } elseif ($nameColumn === null && strcasecmp($trimmed, 'Financial Institution Name') === 0) {
                    $nameColumn = $columnIndex;
                }
            }

            if ($bicColumn !== null && $nameColumn !== null) {
                return [$rowIndex, ['bic' => $bicColumn, 'name' => $nameColumn]];
            }
        }

        return null;
    }

    private static function nullableTrim(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
