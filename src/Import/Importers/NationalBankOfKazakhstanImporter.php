<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\NormalizesStrings;

/**
 * Official-source importer for Kazakhstan (KZ): the National Bank of
 * Kazakhstan's bank directory, published as open data on the government's
 * open-data portal (data.egov.kz).
 *
 * - Landing page: {@see self::sourceUrl()} -- the dataset's catalog page
 *   (`kazakstan_respublikasy_banktik1`) rather than a direct JSON endpoint:
 *   data.egov.kz's actual dataset API requires a portal-issued API key
 *   (`apiKey=...`) this package does not ship, and (like the NBU) the
 *   portal is known to block plain, unauthenticated automated fetches.
 *   **Offline `--file` is therefore the tested/supported path** for this
 *   source -- run `iban:update --source=nbk --file=<downloaded.json>` after
 *   fetching the dataset yourself through the portal.
 * - Documented format: a flat top-level JSON ARRAY (no envelope), one
 *   element per bank, with (assumed, transliterated) keys: `code` (the
 *   3-digit "Code of Bank"), `bic` (falling back to `bik`, the Cyrillic
 *   transliteration some Kazakh sources use for the same 8-character SWIFT
 *   BIC column) and `name`. This field-name mapping is a DOCUMENTED-SHAPE
 *   ASSUMPTION -- the exact live JSON keys have not been confirmed (no
 *   direct, key-less endpoint could be reached); validate against the live
 *   dataset export before production use.
 * - `bank_code` = `code` trimmed and zero-padded to 3 digits (`str_pad`),
 *   matching the 3-digit bank identifier Kazakh IBANs embed, e.g.
 *   `KZ86125KZT5004100100` -> bank code `125`.
 * - **Dedup**: defensive only -- yields the FIRST element seen for each
 *   distinct 3-digit `bank_code`, in case a live export repeats a bank
 *   across several sub-entries the way some other national feeds do.
 * - Encoding: plain JSON is always UTF-8 by specification, so (unlike this
 *   package's CSV/XML importers) no encoding normalization is needed here.
 * - **License**: the National Bank of Kazakhstan does not publish an
 *   explicit reuse/redistribution license for this dataset -- {@see
 *   self::license()} therefore records the issuing body's name itself (the
 *   same "cite the source, no other terms confirmed" convention this
 *   package uses when a source's licensing is unclear, e.g.
 *   {@see NationalBankOfSlovakiaImporter}).
 *
 * CAVEAT: parsing targets a DOCUMENTED-SHAPE ASSUMPTION, not a confirmed
 * live response (the portal requires an API key this package does not
 * ship) -- validate field names against the live official export before
 * production use, and prefer `--file` for a reviewed snapshot.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `json_decode()`)
 * to fetch/parse, per {@see ImporterInterface}'s framework-free contract --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class NationalBankOfKazakhstanImporter implements ImporterInterface
{
    use NormalizesStrings;

    public function countryCode(): string
    {
        return 'KZ';
    }

    public function sourceId(): string
    {
        return 'nbk';
    }

    public function sourceName(): string
    {
        return 'National Bank of Kazakhstan';
    }

    public function license(): string
    {
        return 'National Bank of Kazakhstan (open data)';
    }

    public function sourceUrl(): string
    {
        return 'https://data.egov.kz/datasets/view?index=kazakstan_respublikasy_banktik1';
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

        $data = json_decode($raw, true);

        if (! is_array($data)) {
            return;
        }

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($data as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $rawCode = $entry['code'] ?? null;

            if (is_int($rawCode)) {
                $bankCode = str_pad((string) $rawCode, 3, '0', STR_PAD_LEFT);
            } elseif (is_string($rawCode) && trim($rawCode) !== '') {
                $bankCode = str_pad(trim($rawCode), 3, '0', STR_PAD_LEFT);
            } else {
                continue;
            }

            if (isset($seen[$bankCode])) {
                continue;
            }

            $seen[$bankCode] = true;

            $bic = $entry['bic'] ?? $entry['bik'] ?? null;

            if (is_string($bic)) {
                $bic = strtoupper(trim($bic));
                $bic = $bic !== '' ? $bic : null;
            } else {
                $bic = null;
            }

            $name = $entry['name'] ?? null;

            yield [
                'bank_code'   => $bankCode,
                'branch_code' => null,
                'bic'         => $bic,
                'name'        => is_string($name) ? self::nullableTrim($name) : null,
            ];
        }
    }
}
