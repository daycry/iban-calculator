<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * CURATED-source importer for Vatican City (VA). It yields a small,
 * project-authored FACTUAL map of the 3-digit bank code (this source's
 * `bank_code`, positions 5-7 of a Vatican IBAN) to its bank name and BIC, read
 * from the constant data file {@see __DIR__}/data/va.php.
 *
 * WHY CURATED (not a live/`--file` importer): the Vatican publishes no
 * machine-readable bank directory -- its supervisor (ASIF) lists only a single
 * supervised entity -- and the real universe is exactly ONE bank, the Istituto
 * per le Opere di Religione (IOR, bank code `001`, BIC `IOPRVAVX`), the only
 * institution issuing Vatican IBANs. So the narrow curated-data exception
 * approved as the SEPA-coverage initiative's D4 applies (the same one that
 * already lets Andorra ship via {@see AndorranBankingImporter}). The single
 * fact is INDEPENDENTLY AUTHORED from public facts (bank code, legal name, BIC
 * cross-checked against two official Vatican IBANs), NOT a copy of any source
 * document. See `docs/licensing.md` ("Curated micro-jurisdiction bank data")
 * and `docs/registry-authoring.md` for the facts-vs-compilation rationale.
 *
 * - `rows()` yields the constant `data/va.php` map regardless of `$localFile`
 *   or network state (curated data has no live source to fetch/override).
 * - `bank_code` = the 3-digit code, kept as a STRING so its leading zeros
 *   survive (the Vatican code is `001`).
 * - `bic`: the curated 8-character BIC (IOPRVAVX).
 *
 * LICENSING: {@see self::license()} returns
 * `'curated (factual, non-copyrightable)'` -- stamped onto the seeded row's
 * `source_license`, so the provenance is auditable exactly like any imported
 * row.
 *
 * FRAMEWORK-FREE: uses only native PHP (a `require` of the data file), per
 * {@see ImporterInterface}'s framework-free contract -- even though
 * `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php` (its `Importers/` subtree,
 * where this class and its `data/va.php` live, is).
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see \Daycry\Iban\Import\Importers\AndorranBankingImporter
 * @see docs/licensing.md
 */
final class VaticanCityImporter implements ImporterInterface
{
    public function countryCode(): string
    {
        return 'VA';
    }

    public function sourceId(): string
    {
        return 'vatican';
    }

    public function sourceName(): string
    {
        return 'Vatican City / IOR (curated)';
    }

    public function license(): string
    {
        return 'curated (factual, non-copyrightable)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.asif.va/';
    }

    /**
     * Yields the curated Vatican map. `$localFile` is accepted to satisfy the
     * {@see ImporterInterface} contract but ignored: the data is a constant
     * project-authored factual map, not fetched or overridden from a file.
     *
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        /** @var list<array{bank_code: string, name: string, bic: string|null}> $data */
        $data = require __DIR__ . '/data/va.php';

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
