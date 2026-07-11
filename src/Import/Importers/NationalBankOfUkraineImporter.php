<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Ukraine (UA): the National Bank of Ukraine's
 * (NBU) "bank info" JSON feed, `typ=0` variant -- the authoritative list of
 * settlement participants ("banks"), each identified by its MFO (Ukrainian:
 * "МФО" -- the routing code embedded in Ukrainian IBANs).
 *
 * - Live source: {@see self::sourceUrl()} -- `typ=0&json` returns the BANKS
 *   list (as opposed to `typ=1`'s branch/participant-detail listing).
 *   The NBU portal is known to reject requests without a browser-like
 *   User-Agent/session (403), so a live `iban:update --source=nbu` may need
 *   extra headers this package's plain `file_get_contents()` fetch does not
 *   send -- **offline `--file` is the tested/supported path** for this
 *   source.
 * - Documented format: a flat top-level JSON ARRAY (no envelope), one
 *   element per bank. Each element carries the bank's 6-digit MFO under the
 *   `GLMFO` key (falling back to `MFO` defensively, in case a live response
 *   uses the branch-level key name instead) and the bank's full legal name
 *   under `NGOLOVN` (falling back to `FULLNAME` when `NGOLOVN` is blank).
 *   This field-name mapping is the DOCUMENTED shape this importer was built
 *   against -- the NBU portal blocks automated fetches, so it has not been
 *   confirmed against a live response; validate before production use.
 * - `bank_code` = `GLMFO` (or `MFO`) zero-padded to 6 digits (`str_pad`),
 *   matching the 6-digit MFO Ukrainian IBANs embed as their bank code, e.g.
 *   `UA903052992990004149123456789` -> bank code `305299` (PrivatBank).
 * - No BIC column in this feed: `bic` is left unset/`null`, valid per
 *   {@see ImporterInterface::rows()}'s `bic: string|null, optional` contract.
 * - **Dedup**: defensive only -- yields the FIRST element seen for each
 *   distinct 6-digit `bank_code`, in case a live response repeats an MFO
 *   across several sub-entries the way some other national feeds do.
 * - Encoding: plain JSON is always UTF-8 by specification, so (unlike this
 *   package's CSV/XML importers) no encoding normalization is needed here.
 * - **License**: the NBU does not publish an explicit reuse/redistribution
 *   license for this feed -- {@see self::license()} therefore records the
 *   issuing body's name itself (the same "cite the source, no other terms
 *   confirmed" convention this package uses when a source's licensing is
 *   unclear, e.g. {@see NationalBankOfSlovakiaImporter}).
 *
 * CAVEAT: parsing targets the DOCUMENTED source format -- this importer's
 * field-name assumptions have not been confirmed against a live response
 * (the portal blocks automated fetches); validate against the live official
 * feed before production use, and prefer `--file` for a reviewed snapshot.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `json_decode()`)
 * to fetch/parse, per {@see ImporterInterface}'s framework-free contract --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class NationalBankOfUkraineImporter implements ImporterInterface
{
    public function countryCode(): string
    {
        return 'UA';
    }

    public function sourceId(): string
    {
        return 'nbu';
    }

    public function sourceName(): string
    {
        return 'National Bank of Ukraine';
    }

    public function license(): string
    {
        return 'National Bank of Ukraine (open data)';
    }

    public function sourceUrl(): string
    {
        return 'https://bank.gov.ua/NBU_BankInfo/get_data_branch?typ=0&json';
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

            $rawCode = $entry['GLMFO'] ?? $entry['MFO'] ?? null;

            if (is_int($rawCode)) {
                $bankCode = str_pad((string) $rawCode, 6, '0', STR_PAD_LEFT);
            } elseif (is_string($rawCode) && trim($rawCode) !== '') {
                $bankCode = str_pad(trim($rawCode), 6, '0', STR_PAD_LEFT);
            } else {
                continue;
            }

            if ($bankCode === '000000' || isset($seen[$bankCode])) {
                continue;
            }

            $seen[$bankCode] = true;

            $name = $entry['NGOLOVN'] ?? null;

            if (! is_string($name) || trim($name) === '') {
                $name = $entry['FULLNAME'] ?? null;
            }

            yield [
                'bank_code'   => $bankCode,
                'branch_code' => null,
                'name'        => is_string($name) ? self::nullableTrim($name) : null,
            ];
        }
    }

    private static function nullableTrim(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
