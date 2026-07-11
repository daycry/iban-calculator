<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\ReadsXlsxSource;

/**
 * Official-source importer for Georgia (GE): the National Bank of Georgia's
 * (NBG) RTGS-participants list -- the authoritative registry of every
 * institution's 2-character "IBAN code" (this source's `bank_code`, the
 * segment a Georgian IBAN's BBAN starts with) alongside its RTGS/BIC-style
 * participant code.
 *
 * - Live source: {@see self::sourceUrl()} is NBG's payment-system IBAN
 *   LANDING PAGE (in Georgian: "ბანკების IBAN კოდების ჩამონათვალი"), not a
 *   stable direct file URL -- the actual `.xlsx` linked from that page is
 *   DATE-STAMPED (confirmed live on 2026-07-11 at
 *   `https://nbg.gov.ge/fm/საგადახდო/rtgsმონაწილეები/rtgs-მონაწილეები-geo-190625.xlsx`,
 *   i.e. a filename that changes on every republish) and therefore NOT safe
 *   to hardcode as {@see self::sourceUrl()}. Consumers relying on live fetch
 *   should verify {@see self::rows()} actually yields rows after an update
 *   and fall back to `iban:update --file=<path-to-downloaded-xlsx>` (an
 *   offline import) when the landing page's link target has moved.
 * - Format: no preamble -- the header row is row 1, and its labels are
 *   GEORGIAN ONLY (no English column names, unlike
 *   {@see \Daycry\Iban\Import\Importers\MagyarNemzetiBankImporter}'s source):
 *   confirmed live labels are `მონაწილის დასახელება` ("Participant name"),
 *   `RTGS მონაწილის კოდი` ("RTGS participant code") and `IBAN კოდი` ("IBAN
 *   code"). Rather than hardcode the Georgian header text -- fragile across
 *   minor rewording -- {@see self::locateHeader()} locates the header row by
 *   the Latin-script SUBSTRINGS `RTGS` and `IBAN`, which appear verbatim
 *   inside the (otherwise Georgian) `RTGS მონაწილის კოდი` / `IBAN კოდი`
 *   labels; the one remaining populated column in that row is taken as the
 *   name column.
 * - `bank_code` is the `IBAN კოდი` column verbatim, a 2-character code (e.g.
 *   `'NB'`, `'TB'` -- confirmed against the example
 *   `GE29NB0000000101904917` -> bank `'NB'`, National Bank of Georgia itself,
 *   RTGS code `BNLNGE22`). `bic` maps the `RTGS მონაწილის კოდი` column (an
 *   8-character RTGS/BIC-style code); both columns carry stray leading
 *   whitespace in the live file (e.g. `' BNLNGE22'`, `' HB'`) which is
 *   trimmed here.
 * - **No dedup**: every row in the confirmed live file (21 institutions plus
 *   NBG itself) carries a distinct 2-character `IBAN კოდი` -- no two rows
 *   share the same code.
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
final class NationalBankOfGeorgiaImporter implements ImporterInterface
{
    use ReadsXlsxSource;

    public function countryCode(): string
    {
        return 'GE';
    }

    public function sourceId(): string
    {
        return 'nbg';
    }

    public function sourceName(): string
    {
        return 'National Bank of Georgia';
    }

    public function license(): string
    {
        return 'National Bank of Georgia';
    }

    public function sourceUrl(): string
    {
        return 'https://nbg.gov.ge/en/payment-system/iban';
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
            $bankCode = strtoupper(trim($row[$columns['bank_code']] ?? ''));

            if ($bankCode === '') {
                continue; // blank row or malformed data
            }

            yield [
                'bank_code'   => $bankCode,
                'branch_code' => null,
                'name'        => self::nullableTrim($row[$columns['name']] ?? ''),
                'bic'         => self::nullableUpperTrim($row[$columns['bic']] ?? ''),
            ];
        }
    }

    /**
     * Scans `$grid` for the row carrying the `RTGS`/`IBAN` Latin-script
     * substrings (see the class docblock) -- the header labels are otherwise
     * entirely in Georgian, so exact-string matching (as used by e.g.
     * {@see \Daycry\Iban\Import\Importers\CentralBankOfMaltaImporter}) isn't
     * viable here. Returns the header row's index plus a
     * `name`/`bic`/`bank_code` column-index map, or `null` if no such row
     * exists (e.g. an unexpected layout change).
     *
     * @param list<list<string>> $grid
     *
     * @return array{0: int, 1: array{name: int, bic: int, bank_code: int}}|null
     */
    private static function locateHeader(array $grid): ?array
    {
        foreach ($grid as $rowIndex => $row) {
            $bicColumn  = null;
            $codeColumn = null;
            $nameColumn = null;

            foreach ($row as $columnIndex => $cell) {
                $trimmed = trim($cell);

                if ($trimmed === '') {
                    continue;
                }

                if ($bicColumn === null && stripos($trimmed, 'RTGS') !== false) {
                    $bicColumn = $columnIndex;
                } elseif ($codeColumn === null && stripos($trimmed, 'IBAN') !== false) {
                    $codeColumn = $columnIndex;
                } elseif ($nameColumn === null) {
                    $nameColumn = $columnIndex;
                }
            }

            if ($bicColumn !== null && $codeColumn !== null && $nameColumn !== null) {
                return [$rowIndex, ['name' => $nameColumn, 'bic' => $bicColumn, 'bank_code' => $codeColumn]];
            }
        }

        return null;
    }

    private static function nullableTrim(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function nullableUpperTrim(string $value): ?string
    {
        $trimmed = strtoupper(trim($value));

        return $trimmed !== '' ? $trimmed : null;
    }
}
