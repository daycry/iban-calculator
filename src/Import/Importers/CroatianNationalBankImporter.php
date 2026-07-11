<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\ReadsXlsxSource;

/**
 * Official-source importer for Croatia (HR): the Croatian National Bank's
 * (Hrvatska narodna banka, HNB) "Payment service provider codes" list -- the
 * authoritative registry of every 7-digit VBDI code ("vrsta bankovnog
 * dokumenta identifikacija", this source's `bank_code`, the segment a
 * Croatian IBAN's BBAN starts with) assigned to a payment service provider
 * operating in Croatia.
 *
 * - Live source: {@see self::sourceUrl()} -- confirmed live on 2026-07-11 to
 *   respond with a genuine `.xlsx` (OOXML) spreadsheet directly at this URL
 *   (not an HTML landing page requiring a separate download link to be
 *   located), parsed with {@see XlsxReader} (this package deliberately
 *   avoids a full spreadsheet library -- see `CLAUDE.md`).
 * - Format: a two-row preamble (a title row, then a blank row), then a header
 *   row with these columns (confirmed live): a blank leading column, `
 *   Payment service provider` (name), `Code` (the 7-digit VBDI), `SWIFT
 *   adresa\n(BIC)` (BIC -- the header cell itself contains a literal
 *   newline), and a trailing blank column. Columns are matched by NAME (via
 *   {@see self::locateHeader()}), not position -- the header row isn't at a
 *   fixed index (it follows the two-row preamble).
 * - Already bank-level, one row per provider -- there is no branch/
 *   head-office distinction to filter (unlike
 *   {@see \Daycry\Iban\Import\Importers\OenbImporter}/{@see \Daycry\Iban\Import\Importers\BundesbankImporter}),
 *   and Croatian IBANs carry no branch segment (`branch_code` stays `null`
 *   here). No dedup is needed: every VBDI in this list is unique.
 * - Some rows publish no BIC at all (e.g. e-money institutions without their
 *   own SWIFT connectivity) -- `bic` is left `null` for those, which is
 *   valid per {@see ImporterInterface::rows()}'s `bic: string|null,
 *   optional` contract.
 * - BIC normalization: the live file writes some BIC values with spaces/
 *   trailing whitespace between the SWIFT segments (e.g. `'NBHR HR 2X '`)
 *   -- {@see self::rows()} strips all internal whitespace so `bic` holds a
 *   canonical, space-free BIC (e.g. `'NBHRHR2X'`).
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
final class CroatianNationalBankImporter implements ImporterInterface
{
    use ReadsXlsxSource;

    private const BANK_CODE_PATTERN = '/^[0-9]{7}$/';

    public function countryCode(): string
    {
        return 'HR';
    }

    public function sourceId(): string
    {
        return 'hnb';
    }

    public function sourceName(): string
    {
        return 'Croatian National Bank';
    }

    public function license(): string
    {
        return 'Croatian National Bank (cite source, no changes)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.hnb.hr/documents/d/guest/e-payment-service-provider-codes';
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
            $bankCode = trim($row[$columns['code']] ?? '');

            if (preg_match(self::BANK_CODE_PATTERN, $bankCode) !== 1) {
                continue; // blank row, footer, or malformed data
            }

            yield [
                'bank_code'   => $bankCode,
                'branch_code' => null,
                'name'        => self::nullableTrim($row[$columns['name']] ?? ''),
                'bic'         => self::normalizeBic($row[$columns['bic']] ?? ''),
            ];
        }
    }

    /**
     * Scans `$grid` for the row carrying all three expected header cells --
     * a name column containing `"provider"`, a code column that's EXACTLY
     * `"Code"`, and a BIC column containing `"BIC"` (case-insensitive on all
     * three) -- and returns its index plus a `name`/`code`/`bic`
     * column-index map, or `null` if no such row exists (e.g. an unexpected
     * layout change). Requiring all three in the SAME row is what keeps this
     * from matching the preamble title row, which only repeats the word
     * "provider" without a distinct "Code"/"BIC" cell alongside it.
     *
     * @param list<list<string>> $grid
     *
     * @return array{0: int, 1: array{name: int, code: int, bic: int}}|null
     */
    private static function locateHeader(array $grid): ?array
    {
        foreach ($grid as $rowIndex => $row) {
            $nameColumn = null;
            $codeColumn = null;
            $bicColumn  = null;

            foreach ($row as $columnIndex => $cell) {
                $trimmed = trim($cell);

                if ($nameColumn === null && stripos($trimmed, 'provider') !== false) {
                    $nameColumn = $columnIndex;
                } elseif ($codeColumn === null && strcasecmp($trimmed, 'Code') === 0) {
                    $codeColumn = $columnIndex;
                } elseif ($bicColumn === null && stripos($trimmed, 'BIC') !== false) {
                    $bicColumn = $columnIndex;
                }
            }

            if ($nameColumn !== null && $codeColumn !== null && $bicColumn !== null) {
                return [$rowIndex, ['name' => $nameColumn, 'code' => $codeColumn, 'bic' => $bicColumn]];
            }
        }

        return null;
    }

    private static function nullableTrim(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function normalizeBic(string $value): ?string
    {
        $normalized = strtoupper(str_replace(' ', '', trim($value)));

        return $normalized !== '' ? $normalized : null;
    }
}
