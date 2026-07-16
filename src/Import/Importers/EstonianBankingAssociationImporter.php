<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\NormalizesStrings;
use Daycry\Iban\Import\Support\HtmlTableReader;

/**
 * Official-source importer for Estonia (EE): Eesti Pangaliit's (the Estonian
 * Banking Association) published "bank codes" table, which maps each 2-digit
 * bank code (this source's `bank_code`, the 5th-6th digits of an Estonian
 * IBAN per the FSA's legal definition) to its bank name and BIC.
 *
 * This is the catalog's first HTML-scraping importer: the association
 * publishes the list only as an HTML table (no CSV/XML/JSON anywhere), so it
 * is read with {@see HtmlTableReader}.
 *
 * - Live source: {@see self::sourceUrl()} -- the association's bank-codes
 *   page, fetched with a plain `file_get_contents()` and parsed as HTML. Also
 *   accepts a saved copy via `iban:update --file=...` (the tested path).
 * - Format: a single `<table>` with a header row this importer locates by
 *   name via {@see HtmlTableReader::locateHeader()}. ASSUMPTION: the column
 *   labels are `Bank` / `Bank code` / `BIC` -- validate against the live page
 *   before production use; if the labels differ (e.g. an Estonian-language
 *   edition), the header won't be located and nothing is imported (the
 *   documented HTML-scraping fragility, shared by every importer here).
 * - `bank_code` = the 2-digit code, kept as a STRING so a leading zero
 *   survives (e.g. TBB = `00`). A code cell can list MORE THAN ONE code
 *   (Luminor is assigned both `96` and `17`); every 2-digit code found in the
 *   cell is emitted as its own row sharing the same name + BIC.
 * - `bic`: internal whitespace stripped, upper-cased; blank -> `null`.
 *
 * LICENSING: a factual list of bank codes with no explicit reuse license;
 * consumed under this package's fetch-on-demand posture (the data is not
 * redistributed as a standalone directory), attributed to the association.
 *
 * CAVEAT: HTML scraping is structurally fragile -- validate against the live
 * page before production use; a site redesign can change or remove the table
 * without notice.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`) plus
 * {@see HtmlTableReader} (itself framework-free), per {@see ImporterInterface}'s
 * framework-free contract -- even though `src/Import/` itself isn't guarded
 * by `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 */
final class EstonianBankingAssociationImporter implements ImporterInterface
{
    use NormalizesStrings;

    private const HEADER_NAME = 'Bank';
    private const HEADER_CODE = 'Bank code';
    private const HEADER_BIC  = 'BIC';

    /** Matches each standalone 2-digit code inside a (possibly multi-code) cell. */
    private const CODE_PATTERN = '/(?<!\d)\d{2}(?!\d)/';

    public function countryCode(): string
    {
        return 'EE';
    }

    public function sourceId(): string
    {
        return 'pangaliit';
    }

    public function sourceName(): string
    {
        return 'Eesti Pangaliit (Estonian Banking Association)';
    }

    public function license(): string
    {
        return 'Eesti Pangaliit (factual list)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.pangaliit.ee/settlements-and-standards/bank-codes';
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

        foreach (HtmlTableReader::readTables($raw) as $grid) {
            $header = HtmlTableReader::locateHeader($grid, [self::HEADER_NAME, self::HEADER_CODE, self::HEADER_BIC]);

            if ($header === null) {
                continue; // not the bank-codes table
            }

            [$headerRowIndex, $columns] = $header;

            for ($i = $headerRowIndex + 1, $count = count($grid); $i < $count; $i++) {
                $row  = $grid[$i];
                $name = self::nullableTrim($row[$columns[self::HEADER_NAME]] ?? '');
                $bic  = self::normalizeBic($row[$columns[self::HEADER_BIC]] ?? '');

                foreach (self::extractCodes($row[$columns[self::HEADER_CODE]] ?? '') as $code) {
                    yield [
                        'bank_code'   => $code,
                        'branch_code' => null,
                        'name'        => $name,
                        'bic'         => $bic,
                    ];
                }
            }

            return; // first matching table wins
        }
    }

    /**
     * Extracts every standalone 2-digit code from a cell (Luminor's cell
     * carries two: `96` and `17`).
     *
     * @return list<string>
     */
    private static function extractCodes(string $cell): array
    {
        if (preg_match_all(self::CODE_PATTERN, $cell, $matches) !== false) {
            return $matches[0];
        }

        return [];
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
