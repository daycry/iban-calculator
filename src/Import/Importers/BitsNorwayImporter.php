<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\NormalizesStrings;
use Daycry\Iban\Import\Importers\Concerns\ReadsXlsxSource;

/**
 * Official-source importer for Norway (NO): Bits AS's IBAN bank-identifier
 * list -- the authoritative registry of every 4-digit "Bank identifier" (this
 * source's `bank_code`, the segment a Norwegian IBAN's BBAN starts with)
 * assigned to a Norwegian financial institution.
 *
 * - Live source: {@see self::sourceUrl()} is Bits AS's IBAN LANDING PAGE, not
 *   a stable direct file URL -- the actual `.xlsx` is served from that page
 *   (confirmed live on 2026-07-11: fetching the landing page URL itself
 *   returned a genuine `.xlsx` (OOXML) spreadsheet, so a plain
 *   `file_get_contents()` MAY work directly today, but this is NOT guaranteed
 *   to be stable -- Bits AS could switch to an HTML page with a separate
 *   download link at any time without notice). Consumers relying on live
 *   fetch should verify {@see self::rows()} actually yields rows after an
 *   update and fall back to `iban:update --file=<path-to-downloaded-xlsx>`
 *   (an offline import) if the landing page stops serving the spreadsheet
 *   directly.
 * - Format: no preamble -- the header row is row 1, with these EXACT column
 *   labels (confirmed live): `Bank identifier`, `BIC`, `Bank`. Columns are
 *   matched by NAME (via {@see self::locateHeader()}), not position.
 * - `bank_code` is the `Bank identifier` column verbatim (a 4-digit string,
 *   leading zeros preserved, e.g. `'0500'`, `'8601'` -- confirmed against the
 *   example `NO9386011117947` -> bank `'8601'`, Danske Bank, BIC
 *   `DABANO22`). Only rows whose identifier is exactly 4 digits are
 *   considered; anything else (malformed/blank row) is skipped.
 * - **No dedup**: unlike {@see \Daycry\Iban\Import\Importers\MagyarNemzetiBankImporter},
 *   EVERY 4-digit identifier in this list is its own `bank_code` row, even
 *   when the same legal entity holds several identifiers (e.g. `'DNB Bank
 *   ASA'` legitimately repeats across many rows/identifiers in the live
 *   file -- each one routes independently, since Norwegian IBANs carry no
 *   separate branch segment). No two rows share the same identifier in the
 *   confirmed live file (2,306 rows, 2,306 unique identifiers).
 * - `name` maps the `Bank` column (present and populated for every row in the
 *   confirmed live file, occasionally with trailing whitespace -- trimmed
 *   here); `bic` maps the `BIC` column, uppercased/trimmed.
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
final class BitsNorwayImporter implements ImporterInterface
{
    use NormalizesStrings;
    use ReadsXlsxSource;

    private const HEADER_CODE = 'Bank identifier';
    private const HEADER_BIC  = 'BIC';
    private const HEADER_NAME = 'Bank';

    private const BANK_CODE_PATTERN = '/^[0-9]{4}$/';

    public function countryCode(): string
    {
        return 'NO';
    }

    public function sourceId(): string
    {
        return 'bits';
    }

    public function sourceName(): string
    {
        return 'Bits AS (Norway)';
    }

    public function license(): string
    {
        return 'Bits AS (Norway)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.bits.no/document/iban/';
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
                continue; // blank row, malformed data, or an unexpected identifier shape
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

    /**
     * Uppercases/trims a `BIC` cell and maps an empty value to `null`.
     */
    private static function normalizeBic(string $value): ?string
    {
        $normalized = strtoupper(str_replace(' ', '', trim($value)));

        return $normalized !== '' && $normalized !== 'N/A' ? $normalized : null;
    }
}
