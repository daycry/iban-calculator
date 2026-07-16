<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\NormalizesStrings;
use Daycry\Iban\Import\Support\HtmlTableReader;

/**
 * Official-source importer for Montenegro (ME): the Central Bank of
 * Montenegro's (CBCG) RTGS participants table, which maps each 3-digit
 * participant code (this source's `bank_code`, positions 5-7 of a
 * Montenegrin IBAN) to its name and BIC.
 *
 * - Live source: {@see self::sourceUrl()} -- the CBCG RTGS system page,
 *   fetched with a plain `file_get_contents()` and parsed as HTML via
 *   {@see HtmlTableReader}. Also accepts a saved copy via `iban:update
 *   --file=...` (the tested path).
 * - Format: the CBCG RTGS page carries TWO tables. The participants table
 *   (`No.` / `Participant` / `Fixed no.`) carries no BIC; the one this
 *   importer targets is "Banking identification codes in the RTGS system"
 *   (`No.` / `Bank` / `BIC code` / `Fixed no.`, confirmed live 2026-07-16),
 *   located by name via {@see HtmlTableReader::locateHeader()} -- the
 *   participants table is skipped automatically because it lacks the `Bank`
 *   and `BIC code` labels. The 3-digit code is the `Fixed no.` column.
 * - `bank_code` = the 3-digit participant code, kept as a STRING.
 * - **Public-entity filter**: the RTGS table mixes commercial banks with
 *   public/government participants. Codes in the 714-931 range are
 *   public entities (Treasury, the central bank itself, ...) and are skipped
 *   so only commercial banks are seeded (per the initiative's `research.md`
 *   §ME). Commercial banks sit below 714 (e.g. CKB `510`, Hipotekarna `520`,
 *   Addiko `555`).
 * - `bic`: internal whitespace stripped, upper-cased; blank -> `null`.
 *
 * LICENSING: a factual central-bank participants list; consumed under this
 * package's fetch-on-demand posture, attributed to the CBCG.
 *
 * CAVEAT: HTML scraping is structurally fragile, and the public-entity range
 * boundary (714-931) is itself a documented heuristic -- validate against the
 * live page before production use.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`) plus
 * {@see HtmlTableReader} (itself framework-free), per {@see ImporterInterface}'s
 * framework-free contract -- even though `src/Import/` itself isn't guarded
 * by `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 */
final class CentralBankOfMontenegroImporter implements ImporterInterface
{
    use NormalizesStrings;

    private const HEADER_CODE = 'Fixed no.';
    private const HEADER_NAME = 'Bank';
    private const HEADER_BIC  = 'BIC code';

    private const CODE_PATTERN = '/^[0-9]{3}$/';

    /** Inclusive public-entity code range to exclude (Treasury / central bank / ...). */
    private const PUBLIC_ENTITY_MIN = 714;
    private const PUBLIC_ENTITY_MAX = 931;

    public function countryCode(): string
    {
        return 'ME';
    }

    public function sourceId(): string
    {
        return 'cbcg';
    }

    public function sourceName(): string
    {
        return 'Central Bank of Montenegro';
    }

    public function license(): string
    {
        return 'Central Bank of Montenegro';
    }

    public function sourceUrl(): string
    {
        return 'https://www.cbcg.me/en/core-functions/payment-system/cbcg-payment-system/rtgs-system';
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
            $header = HtmlTableReader::locateHeader($grid, [self::HEADER_CODE, self::HEADER_NAME, self::HEADER_BIC]);

            if ($header === null) {
                continue; // not the participants table
            }

            [$headerRowIndex, $columns] = $header;

            for ($i = $headerRowIndex + 1, $count = count($grid); $i < $count; $i++) {
                $row  = $grid[$i];
                $code = trim($row[$columns[self::HEADER_CODE]] ?? '');

                if (preg_match(self::CODE_PATTERN, $code) !== 1) {
                    continue; // blank/heading/malformed row
                }

                $numeric = (int) $code;

                if ($numeric >= self::PUBLIC_ENTITY_MIN && $numeric <= self::PUBLIC_ENTITY_MAX) {
                    continue; // public/government entity, not a commercial bank
                }

                yield [
                    'bank_code'   => $code,
                    'branch_code' => null,
                    'name'        => self::nullableTrim($row[$columns[self::HEADER_NAME]] ?? ''),
                    'bic'         => self::normalizeBic($row[$columns[self::HEADER_BIC]] ?? ''),
                ];
            }

            return; // first matching table wins
        }
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
