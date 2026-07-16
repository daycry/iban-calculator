<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\ReadsCsvSource;

/**
 * Official-source importer for Finland (FI): Finance Finland's (Finanssiala ry)
 * "Suomalaiset rahalaitostunnukset ja BIC-koodit" (Finnish financial-institution
 * codes and BIC codes) directory, which lists each institution's name
 * (`Rahalaitos`), its `Rahalaitostunnus` code(s), and BIC -- e.g. Nordea Bank
 * Abp ("1 ja 2" / NDEAFIHH), OP Osuuskunta ("5" / OKOYFIHH), Danske Bank
 * ("8 ja 34" / DABAFIHH).
 *
 * OFFLINE `--file` ONLY. The authoritative source is a PDF, and this package
 * deliberately ships no PDF reader; a live, no-`$localFile` call fetches the
 * landing bytes (HTML, not the directory) which carry no
 * `Rahalaitos;Rahalaitostunnus;BIC` header, so it gracefully yields nothing.
 * The supported workflow is `iban:update --source=finanssiala --country=FI
 * --file=<csv>` with an operator-prepared CSV.
 *
 * PREPARATION RECIPE (operator-side, documented so the `--file` input is
 * reproducible):
 *
 *   1. Extract the PDF's text preserving column alignment:
 *        pdftotext -layout -enc UTF-8 "suomalaiset-rahalaitostunnukset-ja-bic-<date>.pdf" fi.txt
 *   2. Tidy the three columns into a SEMICOLON-delimited UTF-8 CSV with the
 *      header `Rahalaitos;Rahalaitostunnus;BIC`, one row per institution --
 *      semicolon (not comma), because a `Rahalaitostunnus` cell can itself
 *      contain a comma-separated list (e.g. `405, 497`).
 *
 * THE RANGE-EXPANSION MAPPER (the point of this importer). Finland's IBAN
 * `bank_code` is a FIXED 3-digit field (positions 5-7), but this source keys
 * institutions by a VARIABLE-length `Rahalaitostunnus` -- a single value (`5`),
 * a multi-value list (`1 ja 2`, `8 ja 34`, `405, 497`), a range (`470-479`), or
 * a long comma/hyphen list. {@see self::rows()} splits each cell on the Finnish
 * conjunction `ja` and on commas, then expands each token into the 3-digit
 * codes it covers ({@see self::expandToken()}):
 * - a 1-digit token `d` covers `d00`..`d99` (100 codes);
 * - a 2-digit token `de` covers `de0`..`de9` (10 codes);
 * - a 3-digit token `def` is the single code `def`;
 * - a range `abc-xyz` (3-digit endpoints) covers every code in `[abc, xyz]`.
 *
 * DOCUMENTED LIMITATION -- 4-digit codes are LOST. Since 2024 the codes whose
 * leading digit is 72-78 are FOUR digits long (e.g. `7180`), which cannot be
 * keyed on the fixed 3-digit `bank_code`. Such a token is deliberately dropped,
 * surfaced as a `bank_code`-less row so the {@see \Daycry\Iban\Import\ImportReport}
 * counts it in `skipped`/`messages` -- the loss is visible, not silent.
 *
 * DE-DUPLICATION. A 3-digit code is claimed by the FIRST institution to cover
 * it; a later token (from any institution) that would re-claim it is skipped,
 * so overlapping ranges never collide and the earliest name/BIC wins.
 *
 * LICENSING: Finanssiala ry states NO reuse terms for this directory, so it is
 * treated strictly as fetch-only under this package's `--file` posture -- the
 * data is NOT bundled, only mapped on demand from an operator-supplied file,
 * attributed to Finance Finland.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`,
 * `preg_*`, `str_pad()`, `mb_*`), per {@see ImporterInterface}'s framework-free
 * contract -- even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`. Requires `ext-mbstring`
 * (declared in `composer.json`).
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 */
final class FinanceFinlandImporter implements ImporterInterface
{
    use ReadsCsvSource;

    /**
     * Header substring identifying the code column. Deliberately NOT
     * `rahalaitos` (which is a substring of `Rahalaitostunnus` too) -- `tunnus`
     * is unique to the code column, so a `Rahalaitos` (name) header cell never
     * matches it.
     *
     * @var list<string>
     */
    private const HEADER_CODE_TOKENS = ['tunnus'];

    /** @var list<string> Header substrings identifying the name column. */
    private const HEADER_NAME_TOKENS = ['rahalaitos', 'nimi'];

    /** @var list<string> Header substrings identifying the BIC column. */
    private const HEADER_BIC_TOKENS = ['bic', 'swift'];

    public function countryCode(): string
    {
        return 'FI';
    }

    public function sourceId(): string
    {
        return 'finanssiala';
    }

    public function sourceName(): string
    {
        return 'Finance Finland (Finanssiala ry)';
    }

    public function license(): string
    {
        return 'Finanssiala ry (no reuse terms; fetch-only)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.finanssiala.fi/julkaisut/suomalaiset-rahalaitostunnukset-ja-bic-koodit/';
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

        /** @var array<string, true> $claimed 3-digit codes already yielded (first claim wins). */
        $claimed = [];

        for ($i = $headerRowIndex + 1, $count = count($records); $i < $count; $i++) {
            $row  = $records[$i];
            $spec = trim($row[$columns['code']] ?? '');

            if ($spec === '') {
                continue; // not a data row
            }

            $name = self::nullableTrim($row[$columns['name']] ?? '');
            $bic  = self::normalizeBic($row[$columns['bic']] ?? '');

            foreach (self::tokenize($spec) as $token) {
                $codes = self::expandToken($token);

                if ($codes === null) {
                    // A 4-digit (post-2024, leading 72-78) or otherwise
                    // un-keyable token: cannot map to a fixed 3-digit
                    // bank_code. Yield it WITHOUT a bank_code so the
                    // ImportRunner counts it in the report's skipped/messages
                    // (documented, visible loss -- see the class docblock).
                    yield [
                        'bank_code'   => null,
                        'branch_code' => null,
                        'name'        => $name,
                        'bic'         => $bic,
                    ];

                    continue;
                }

                foreach ($codes as $code) {
                    if (isset($claimed[$code])) {
                        continue; // de-dup: the first institution to claim a code wins
                    }

                    $claimed[$code] = true;

                    yield [
                        'bank_code'   => $code,
                        'branch_code' => null,
                        'name'        => $name,
                        'bic'         => $bic,
                    ];
                }
            }
        }
    }

    /**
     * Splits a `Rahalaitostunnus` cell into its individual tokens, on the
     * Finnish conjunction `ja` and on commas (a range like `470-479` stays a
     * single token). Blank tokens are dropped.
     *
     * @return list<string>
     */
    private static function tokenize(string $spec): array
    {
        $parts  = preg_split('/\s*,\s*|\s+ja\s+/u', $spec) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part !== '') {
                $tokens[] = $part;
            }
        }

        return $tokens;
    }

    /**
     * Expands one token into the list of fixed 3-digit `bank_code`s it covers,
     * or `null` when the token cannot be keyed on 3 digits (a 4+-digit code, or
     * anything not a plain number / 3-digit range) -- the caller surfaces a
     * `null` as a skipped row.
     *
     * @return list<string>|null
     */
    private static function expandToken(string $token): ?array
    {
        // A range abc-xyz with 3-digit endpoints -> every code in [abc, xyz].
        if (preg_match('/^(\d{3})-(\d{3})$/', $token, $m) === 1) {
            $start = (int) $m[1];
            $end   = (int) $m[2];

            if ($start > $end) {
                return null; // malformed range -> skip
            }

            $codes = [];

            for ($code = $start; $code <= $end; $code++) {
                $codes[] = str_pad((string) $code, 3, '0', STR_PAD_LEFT);
            }

            return $codes;
        }

        if (preg_match('/^\d+$/', $token) !== 1) {
            return null; // not a plain number we understand -> skip
        }

        $length = strlen($token);

        if ($length === 1) {
            // `d` -> d00..d99 (100 codes).
            $codes = [];

            for ($i = 0; $i < 100; $i++) {
                $codes[] = $token . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            }

            return $codes;
        }

        if ($length === 2) {
            // `de` -> de0..de9 (10 codes).
            $codes = [];

            for ($i = 0; $i < 10; $i++) {
                $codes[] = $token . (string) $i;
            }

            return $codes;
        }

        if ($length === 3) {
            return [$token]; // the single code `def`.
        }

        // 4+ digits (post-2024, leading 72-78): cannot be keyed on a fixed
        // 3-digit bank_code -> skip (documented limitation).
        return null;
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
     * multibyte-aware for the Finnish labels).
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
