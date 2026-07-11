<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Support\XlsxReader;
use RuntimeException;

/**
 * Official-source importer for Hungary (HU): the Magyar Nemzeti Bank's (MNB)
 * GIRO routing table ("sht.xlsx") -- the authoritative registry every
 * institution's GIRO branch-office codes are drawn from, of which the first 3
 * digits are this source's `bank_code` (the segment a Hungarian IBAN's BBAN
 * starts with).
 *
 * - Live source: {@see self::sourceUrl()} -- confirmed live on 2026-07-11 to
 *   be a genuine `.xlsx` (OOXML) spreadsheet, parsed with {@see XlsxReader}
 *   (this package deliberately avoids a full spreadsheet library -- see
 *   `CLAUDE.md`).
 * - Format: no preamble -- the header row is row 1, with these EXACT column
 *   labels (confirmed live, and -- CAVEAT/SURPRISE -- in ENGLISH, not
 *   Hungarian, despite the source itself being Hungarian): `Branch office
 *   code`, `BIC code`, `Name of the branch office`, `Address of the branch
 *   office`, `Branch office may send VIBER items`, `Branch office may receive
 *   VIBER items`. Only `Branch office code`, `BIC code` and `Name of the
 *   branch office` are used here. Columns are matched by NAME (via
 *   {@see self::locateHeader()}), not position.
 * - **`bank_code` derivation -- CAVEAT/SURPRISE, an 8-digit code, not 7**:
 *   the live file's `Branch office code` is confirmed to be an 8-DIGIT code
 *   for every one of its ~6,700 rows (e.g. `'11701004'`), not the 7-digit
 *   (3-digit bank + 4-digit branch) shape originally assumed -- it is in fact
 *   3-digit bank + 4-digit branch + 1-digit trailing check/sequence digit
 *   (e.g. `'117'` + `'0100'` + `'4'`). This does not change the derivation
 *   rule, though: `bank_code` is still the FIRST 3 CHARACTERS of the code
 *   (e.g. `'11701004'` -> `'117'`), which is the segment a Hungarian IBAN's
 *   BBAN actually starts with (confirmed against the example
 *   `HU42117730161111101800000000` -> bank `'117'`, GIRO code holder OTP Bank
 *   Nyrt., BIC `OTPVHUHB`) -- only rows whose code is ALL-DIGITS and at least
 *   3 characters long are considered; anything else (malformed/blank row) is
 *   skipped.
 * - **Dedup by the 3-digit `bank_code`**: this source lists ONE ROW PER
 *   PHYSICAL BRANCH OFFICE (thousands of rows for ~179 unique 3-digit bank
 *   codes) -- {@see self::rows()} yields exactly ONE row per unique 3-digit
 *   prefix, keeping the FIRST occurrence's `name`/`bic` (in file order) and
 *   silently skipping every later row sharing that prefix, since the `banks`
 *   table is keyed on `(country_code, bank_code, branch_code)` and a second
 *   insert would just be a no-op duplicate at best.
 * - **No separate bank-level name column**: unlike most other sources in
 *   this package, MNB's routing table has no column naming the INSTITUTION
 *   (only `Name of the branch office`, e.g. `'OTP Budapesti r., I. Iskola
 *   u.'`) -- the bank-level `name` this importer yields is therefore simply
 *   the FIRST occurrence's branch name for that 3-digit prefix, which is
 *   whatever branch happens to sort first in the source file for that bank
 *   (not necessarily a clean "head office" label). Consumers wanting a
 *   canonical institution name for HU should not rely on this column being a
 *   polished bank name.
 * - BIC normalization: uppercased/trimmed for consistency (the live file's
 *   `BIC code` values are already space-free and fully populated -- no empty
 *   cells were observed in the confirmed live file).
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
final class MagyarNemzetiBankImporter implements ImporterInterface
{
    private const HEADER_CODE = 'Branch office code';
    private const HEADER_BIC  = 'BIC code';
    private const HEADER_NAME = 'Name of the branch office';

    private const BANK_CODE_LENGTH = 3;

    private const CODE_PATTERN = '/^[0-9]{3,}$/';

    public function countryCode(): string
    {
        return 'HU';
    }

    public function sourceId(): string
    {
        return 'mnb';
    }

    public function sourceName(): string
    {
        return 'Magyar Nemzeti Bank';
    }

    public function license(): string
    {
        return 'Magyar Nemzeti Bank';
    }

    public function sourceUrl(): string
    {
        return 'https://www.mnb.hu/letoltes/sht.xlsx';
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        if ($localFile !== null) {
            $path = $localFile;
            $tmp  = null;
        } else {
            $raw = @file_get_contents($this->sourceUrl());

            if ($raw === false || $raw === '') {
                return;
            }

            $tmp = tempnam(sys_get_temp_dir(), 'iban_xlsx_');

            if ($tmp === false) {
                return;
            }

            file_put_contents($tmp, $raw);
            $path = $tmp;
        }

        try {
            $grid = XlsxReader::readFirstSheet($path);
        } catch (RuntimeException) {
            if ($tmp !== null) {
                @unlink($tmp);
            }

            return;
        }

        $header = self::locateHeader($grid);

        if ($header === null) {
            if ($tmp !== null) {
                @unlink($tmp);
            }

            return;
        }

        [$headerRowIndex, $columns] = $header;

        /** @var array<string, true> $seen */
        $seen = [];

        for ($i = $headerRowIndex + 1, $count = count($grid); $i < $count; $i++) {
            $row  = $grid[$i];
            $code = trim($row[$columns['code']] ?? '');

            if (preg_match(self::CODE_PATTERN, $code) !== 1) {
                continue; // blank row, malformed data, or a non-numeric footer/note row
            }

            $bankCode = substr($code, 0, self::BANK_CODE_LENGTH);

            if (isset($seen[$bankCode])) {
                continue; // same institution, a different branch office already seen
            }

            $seen[$bankCode] = true;

            yield [
                'bank_code'   => $bankCode,
                'branch_code' => null,
                'name'        => self::nullableTrim($row[$columns['name']] ?? ''),
                'bic'         => self::normalizeBic($row[$columns['bic']] ?? ''),
            ];
        }

        if ($tmp !== null) {
            @unlink($tmp);
        }
    }

    /**
     * Scans `$grid` for the row carrying every expected header label (see the
     * class docblock), and returns its index plus a `code`/`bic`/`name`
     * column-index map -- or `null` if no such row exists (e.g. an unexpected
     * layout change).
     *
     * @param list<list<string>> $grid
     *
     * @return array{0: int, 1: array{code: int, bic: int, name: int}}|null
     */
    private static function locateHeader(array $grid): ?array
    {
        foreach ($grid as $rowIndex => $row) {
            $codeColumn = null;
            $bicColumn  = null;
            $nameColumn = null;

            foreach ($row as $columnIndex => $cell) {
                $trimmed = trim($cell);

                if ($codeColumn === null && strcasecmp($trimmed, self::HEADER_CODE) === 0) {
                    $codeColumn = $columnIndex;
                } elseif ($bicColumn === null && strcasecmp($trimmed, self::HEADER_BIC) === 0) {
                    $bicColumn = $columnIndex;
                } elseif ($nameColumn === null && strcasecmp($trimmed, self::HEADER_NAME) === 0) {
                    $nameColumn = $columnIndex;
                }
            }

            if ($codeColumn !== null && $bicColumn !== null && $nameColumn !== null) {
                return [$rowIndex, ['code' => $codeColumn, 'bic' => $bicColumn, 'name' => $nameColumn]];
            }
        }

        return null;
    }

    private static function nullableTrim(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Uppercases/trims a `BIC code` cell and maps an empty/`'N/A'` value to
     * `null`.
     */
    private static function normalizeBic(string $value): ?string
    {
        $normalized = strtoupper(str_replace(' ', '', trim($value)));

        return $normalized !== '' && $normalized !== 'N/A' ? $normalized : null;
    }
}
