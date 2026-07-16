<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * CURATED-source importer for Andorra (AD): the catalog's first curated
 * importer. It yields a small, project-authored FACTUAL map of every 4-digit
 * "Entitat" bank code (this source's `bank_code`, the first 4 digits of an
 * Andorran BBAN) to its bank name and BIC, read from the constant data file
 * {@see __DIR__}/data/ad.php.
 *
 * WHY CURATED (not a live/`--file` importer): Andorra's only public directory
 * is a rotating-URL PDF ("Codificació de les oficines bancàries - Format
 * IBAN"), and this package deliberately ships no PDF reader. But the real
 * universe is just THREE banks / FOUR codes -- a tiny, stable set -- so the
 * narrow curated-data exception approved as the SEPA-coverage initiative's D4
 * applies. The map is INDEPENDENTLY AUTHORED from public facts (Entitat code,
 * legal name, BIC cross-checked against EPC/SWIFT), NOT a copy of any source
 * document. See `docs/licensing.md` ("Curated micro-jurisdiction bank data")
 * and `docs/registry-authoring.md` for the facts-vs-compilation rationale --
 * the same one that already lets `src/Registry/data/countries.php` ship.
 *
 * - `rows()` yields the constant `data/ad.php` map regardless of `$localFile`
 *   or network state (curated data has no live source to fetch/override).
 * - `bank_code` = the 4-digit Entitat code, kept as a STRING so its leading
 *   zeros survive (every Andorran code starts `000`). MoraBanc holds both
 *   `0007` and `0008`, each yielded as its own row sharing the same name + BIC.
 * - `bic`: the curated 8-character BIC (BACAADAD / CRDAADAD / BSAAADAD).
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
 * where this class and its `data/ad.php` live, is).
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/licensing.md
 */
final class AndorranBankingImporter implements ImporterInterface
{
    public function countryCode(): string
    {
        return 'AD';
    }

    public function sourceId(): string
    {
        return 'andorran-banking';
    }

    public function sourceName(): string
    {
        return 'Andorran Banking (curated)';
    }

    public function license(): string
    {
        return 'curated (factual, non-copyrightable)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.andorranbanking.ad/';
    }

    /**
     * Yields the curated Andorra map. `$localFile` is accepted to satisfy the
     * {@see ImporterInterface} contract but ignored: the data is a constant
     * project-authored factual map, not fetched or overridden from a file.
     *
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        /** @var list<array{bank_code: string, name: string, bic: string|null}> $data */
        $data = require __DIR__ . '/data/ad.php';

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
