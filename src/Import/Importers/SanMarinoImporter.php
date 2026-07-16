<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * CURATED-source importer for San Marino (SM). It yields a small,
 * project-authored FACTUAL map of the 5-digit ABI bank code (this source's
 * `bank_code`, positions 5-9 of a Sammarinese IBAN, after the 1-letter
 * national check) to its bank name and BIC, read from the constant data file
 * {@see __DIR__}/data/sm.php.
 *
 * WHY CURATED (not a live/scraping importer): San Marino uses the Italian ABI
 * numbering, but the Italian ABI directories do NOT list Sammarinese banks --
 * the only source is the Banca Centrale della Repubblica di San Marino's
 * "Operating Banks" HTML page, a tiny, stable set of FOUR banks. Rather than
 * scrape four rows, the narrow curated-data exception approved as the
 * SEPA-coverage initiative's D4 applies (the same one that already lets Andorra
 * ship via {@see AndorranBankingImporter}). The four rows are INDEPENDENTLY
 * AUTHORED from public facts (ABI code, legal name, BIC cross-checked against
 * EPC/SWIFT), NOT a copy of any source document. See `docs/licensing.md`
 * ("Curated micro-jurisdiction bank data") and `docs/registry-authoring.md`.
 *
 * - `rows()` yields the constant `data/sm.php` map regardless of `$localFile`
 *   or network state (curated data has no live source to fetch/override).
 * - `bank_code` = the 5-digit ABI code, kept as a STRING so its leading zeros
 *   survive (Sammarinese ABIs start `0` -- e.g. `03034`).
 * - `bic`: the curated 8-character BIC (BASMSMSM / MAOISMSM / BSDISMSD /
 *   CSSMSMSM).
 *
 * LICENSING: {@see self::license()} returns
 * `'curated (factual, non-copyrightable)'` -- stamped onto every seeded row's
 * `source_license`, so the provenance is auditable exactly like any imported
 * row.
 *
 * FRAMEWORK-FREE: uses only native PHP (a `require` of the data file), per
 * {@see ImporterInterface}'s framework-free contract -- even though
 * `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php` (its `Importers/` subtree,
 * where this class and its `data/sm.php` live, is).
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see \Daycry\Iban\Import\Importers\AndorranBankingImporter
 * @see docs/licensing.md
 */
final class SanMarinoImporter implements ImporterInterface
{
    public function countryCode(): string
    {
        return 'SM';
    }

    public function sourceId(): string
    {
        return 'bcsm';
    }

    public function sourceName(): string
    {
        return 'Banca Centrale di San Marino (curated)';
    }

    public function license(): string
    {
        return 'curated (factual, non-copyrightable)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.bcsm.sm/';
    }

    /**
     * Yields the curated San Marino map. `$localFile` is accepted to satisfy
     * the {@see ImporterInterface} contract but ignored: the data is a constant
     * project-authored factual map, not fetched or overridden from a file.
     *
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        /** @var list<array{bank_code: string, name: string, bic: string|null}> $data */
        $data = require __DIR__ . '/data/sm.php';

        foreach ($data as $entry) {
            yield [
                'bank_code'   => $entry['bank_code'],
                'branch_code' => null,
                'name'        => $entry['name'],
                'bic'         => $entry['bic'],
            ];
        }
    }
}
