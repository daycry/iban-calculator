<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for the Netherlands (NL): Betaalvereniging
 * Nederland's "BIC-lijst NL" (BIC list), a voluntary, non-exhaustive
 * directory of Dutch payment service providers' 4-letter "Identifier"
 * codes (this source's `bank_code` -- the segment a Dutch IBAN's BBAN
 * starts with) alongside their BIC and name.
 *
 * - **Source format caveat**: Betaalvereniging Nederland publishes this
 *   list as an **.xlsx spreadsheet** (see {@see self::sourceUrl()}), not a
 *   CSV -- and this package deliberately does not depend on a
 *   spreadsheet-parsing library (e.g. PhpSpreadsheet) to read it. This
 *   importer therefore consumes a **CSV exported from that spreadsheet**
 *   (e.g. "Save As CSV" from Excel/LibreOffice), not the .xlsx itself. A
 *   live, no-`$localFile` call to {@see self::rows()} fetches the raw
 *   `sourceUrl()` bytes (an .xlsx, i.e. a ZIP archive) and attempts to
 *   parse them as CSV -- which will not yield any matching rows (see the
 *   Identifier filter below), failing gracefully to an empty iterable
 *   rather than throwing. The supported workflow is therefore
 *   `iban:update --source=betaalvereniging --country=NL --file=<csv>` with
 *   a CSV the consumer exported themselves.
 * - Format (of the CSV export this importer targets): `;`-delimited,
 *   UTF-8.
 * - **Two-row preamble**: the sheet's row 1 is a merged-cell title
 *   ("BIC-lijst-NL | BIC-list-NL (Laatste update | last update ...)") and
 *   row 2 is the column header (`BIC;Identifier;Betaaldienstverlener /
 *   Payment Service Provider` -- note the source's header cell carries a
 *   trailing space). Rather than special-casing "skip the first 2 lines",
 *   {@see self::rows()} uses a single robust rule that skips both (and any
 *   other non-data row) at once: a line is treated as DATA only if its
 *   `Identifier` field (idx 1) matches `^[A-Z]{4}$` -- the title row's
 *   idx 1 is empty and the header row's idx 1 is the literal string
 *   `"Identifier"`, so neither matches.
 * - Columns are matched by FIXED POSITION (0-based): `0`=BIC,
 *   `1`=Identifier (`bank_code`), `2`=name (Payment Service Provider,
 *   taken verbatim -- not synthesized, since some rows repeat the
 *   identifier or a parent bank's name).
 * - Encoding: the source is UTF-8 (current data is plain ASCII, so there's
 *   no real accented-character risk today, but a defensive leading-BOM
 *   strip is applied regardless since a spreadsheet CSV export may add
 *   one).
 *
 * CAVEAT: parsing targets the documented/observed CSV-export layout as of
 * this release -- validate against a freshly-exported file before
 * production use; the source spreadsheet's column order/preamble could
 * change without notice.
 *
 * LICENSING: this list is published free of charge and without a login,
 * but it is NOT open data -- Betaalvereniging Nederland's terms reserve
 * copyright, permit personal/non-commercial use only, and prohibit
 * reproducing or redistributing the list without written permission. The
 * safe design followed here is: the consumer downloads/exports the file
 * themselves and imports it locally via `--file`; this package does not
 * bundle or ship the underlying data. The list is also voluntary/
 * non-exhaustive -- absence from it doesn't mean an Identifier is invalid.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`)
 * to fetch/parse, per {@see ImporterInterface}'s framework-free contract --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class BetaalverenigingImporter implements ImporterInterface
{
    private const INDEX_BIC        = 0;
    private const INDEX_IDENTIFIER = 1;
    private const INDEX_NAME       = 2;

    private const IDENTIFIER_PATTERN = '/^[A-Z]{4}$/';

    public function countryCode(): string
    {
        return 'NL';
    }

    public function sourceId(): string
    {
        return 'betaalvereniging';
    }

    public function sourceName(): string
    {
        return 'Betaalvereniging Nederland';
    }

    public function license(): string
    {
        return 'Betaalvereniging Nederland (see terms)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.betaalvereniging.nl/wp-content/uploads/BIC-lijst-NL.xlsx';
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        $raw = $localFile !== null
            ? @file_get_contents($localFile)
            : @file_get_contents($this->sourceUrl());

        if ($raw === false || $raw === '') {
            return;
        }

        $raw = self::stripBom($raw);

        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            return;
        }

        fwrite($stream, $raw);
        rewind($stream);

        while (($fields = fgetcsv($stream, 0, ';')) !== false) {
            if ($fields === [null]) {
                continue; // blank line
            }

            $identifier = trim($fields[self::INDEX_IDENTIFIER] ?? '');

            if (preg_match(self::IDENTIFIER_PATTERN, $identifier) !== 1) {
                continue; // title/header/preamble row, or malformed data
            }

            yield [
                'bank_code'   => $identifier,
                'branch_code' => null,
                'bic'         => self::nullableTrim($fields[self::INDEX_BIC] ?? ''),
                'name'        => self::nullableTrim($fields[self::INDEX_NAME] ?? ''),
            ];
        }

        fclose($stream);
    }

    private static function nullableTrim(?string $value): ?string
    {
        $trimmed = trim($value ?? '');

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Strips a leading UTF-8 BOM if present. No Latin-1 fallback is applied:
     * this source is UTF-8.
     */
    private static function stripBom(string $raw): string
    {
        return str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
    }
}
