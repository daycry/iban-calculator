<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\NormalizesStrings;
use Daycry\Iban\Import\Support\HtmlTableReader;

/**
 * Official-source importer for Italy (IT): the Agenzia delle Entrate F24
 * "elenco banche convenzionate" (list of banks party to the F24 tax-payment
 * convention) page, which maps each `Codice ABI` (this source's `bank_code`,
 * positions 5-9 of an Italian IBAN, after the 1-letter national check) to its
 * `Denominazione` (bank name). NAME ONLY -- this source carries no BIC.
 *
 * - Live source: {@see self::sourceUrl()} -- the F24 by-code listing
 *   (`...-xcodice`), fetched with a plain `file_get_contents()` and parsed as
 *   HTML via {@see HtmlTableReader}. Also accepts a saved copy via `iban:update
 *   --file=...` (the tested path).
 * - Format: a single `<table>` whose header row this importer locates by name
 *   via {@see HtmlTableReader::locateHeader()}. The column labels targeted are
 *   `Codice ABI` and `Denominazione` (per the initiative's `research.md` §IT).
 *   If a site redesign changes the labels the header won't be located and
 *   nothing is imported (the documented HTML-scraping fragility shared by every
 *   importer here).
 * - `bank_code`: the `Codice ABI` is shown WITHOUT leading zeros on this page
 *   (e.g. `3069`), so it is **zero-padded to 5 digits** (`03069`) and kept as a
 *   STRING to match the structural registry's IBAN bank segment. A cell that
 *   isn't 1-5 digits is skipped.
 * - `bic`: always `null` -- the F24 page has no BIC column (BIC enrichment
 *   would come from a separate EPC/SWIFT layer, deferred).
 *
 * COVERAGE CAVEAT: this list is PARTIAL by nature -- it covers only the banks
 * party to the F24 convention (~400), not every ABI-registered Italian bank.
 * The canonical, exhaustive ABI/CAB directory (SIA-Nexi) is a paid
 * subscription (tier D), so this is the best openly-fetchable mapping. See
 * `docs/importers.md`.
 *
 * LICENSING: no explicit reuse license is declared for the F24 page; consumed
 * under this package's fetch-on-demand posture (the data is not redistributed
 * as a standalone directory), attributed to the Agenzia delle Entrate.
 *
 * CAVEAT: HTML scraping is structurally fragile -- validate against the live
 * page before production use; a site redesign can change or remove the table
 * without notice.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`) plus
 * {@see HtmlTableReader} (itself framework-free), per {@see ImporterInterface}'s
 * framework-free contract -- even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 */
final class AgenziaEntrateF24Importer implements ImporterInterface
{
    use NormalizesStrings;

    private const HEADER_CODE = 'Codice ABI';
    private const HEADER_NAME = 'Denominazione';

    /** The ABI is shown without leading zeros; accept 1-5 digits, then zero-pad to 5. */
    private const CODE_PATTERN = '/^\d{1,5}$/';

    public function countryCode(): string
    {
        return 'IT';
    }

    public function sourceId(): string
    {
        return 'agenzia-entrate';
    }

    public function sourceName(): string
    {
        return 'Agenzia delle Entrate (F24)';
    }

    public function license(): string
    {
        return 'Agenzia delle Entrate (F24, partial list)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.agenziaentrate.gov.it/portale/schede/pagamenti/f24/elenco-banche-convenzionate-f24/elenco-banche-f24-xcodice';
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
            $header = HtmlTableReader::locateHeader($grid, [self::HEADER_CODE, self::HEADER_NAME]);

            if ($header === null) {
                continue; // not the banks table
            }

            [$headerRowIndex, $columns] = $header;

            for ($i = $headerRowIndex + 1, $count = count($grid); $i < $count; $i++) {
                $row  = $grid[$i];
                $code = trim($row[$columns[self::HEADER_CODE]] ?? '');

                if (preg_match(self::CODE_PATTERN, $code) !== 1) {
                    continue; // blank/heading/malformed row
                }

                yield [
                    'bank_code'   => str_pad($code, 5, '0', STR_PAD_LEFT),
                    'branch_code' => null,
                    'name'        => self::nullableTrim($row[$columns[self::HEADER_NAME]] ?? ''),
                    'bic'         => null, // this source carries no BIC
                ];
            }

            return; // first matching table wins
        }
    }
}
