<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Israel (IL): the Bank of Israel's national
 * bank/branch directory ("snifim"), published as open data on the Israeli
 * government's open-data portal (data.gov.il) via its CKAN datastore API.
 *
 * - Live source: {@see self::sourceUrl()} -- confirmed live 2026-07-11, a
 *   CKAN `datastore_search` call against resource_id
 *   `6f3bda2a-8cde-4b86-a1c8-2761862b1224` (the English-keyed bank/branch
 *   directory resource) with `limit=100000` to fetch the whole table in one
 *   request (confirmed live: a couple thousand branch rows).
 * - Format: CKAN JSON envelope `{ help, success, result: { records: [...] } }`.
 *   Each record is per-BRANCH ("snif"), with these confirmed-live English
 *   keys: `Bank_Code` (string, e.g. `"1"`, `"3"`, `"10"` -- NOT zero-padded
 *   in the source), `Bank_Name`, `Branch_Code` (numeric), `Branch_Name`,
 *   plus address/geocoding columns this importer does not use.
 * - **Bank-level rollup + dedup**: the source publishes one row per physical
 *   branch (confirmed live: e.g. `Bank_Code` `"10"`, Bank Leumi, repeats
 *   across dozens of `Branch_Code` rows). Since `banks` is keyed on
 *   `(country_code, bank_code, branch_code)` with `branch_code` always
 *   `null` here, {@see self::rows()} yields only the FIRST branch row seen
 *   for each distinct `Bank_Code`.
 * - `bank_code` = `Bank_Code` zero-padded to 3 digits (`str_pad`), matching
 *   the 3-digit bank identifier Israeli IBANs embed, e.g.
 *   `IL620108000000099999999` -> bank code `010` (Bank Leumi, source
 *   `Bank_Code` `"10"`, confirmed live against this same API).
 * - No BIC column: this resource does not publish a BIC/SWIFT code per bank
 *   (Bank of Israel maintains that separately) -- `bic` is therefore left
 *   unset/`null`, valid per {@see ImporterInterface::rows()}'s
 *   `bic: string|null, optional` contract.
 * - Encoding: confirmed live to be genuine UTF-8, no BOM; some fields
 *   (`Handicap_Access`, `day_closed`) carry Hebrew text this importer does
 *   not consume.
 * - **License**: data.gov.il publishes this dataset under its portal-wide
 *   "other-open" (custom, mostly-open) license rather than a named
 *   CC-BY/PDDL license -- {@see self::license()} records that alongside the
 *   issuing body per this package's "cite the source" convention (see e.g.
 *   {@see NationalBankOfSlovakiaImporter}).
 *
 * CAVEAT: parsing targets the documented/observed source format as of this
 * release -- validate against the live official endpoint before production
 * use; issuing bodies change layouts (and, for CKAN portals, resource IDs)
 * without notice.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `json_decode()`)
 * to fetch/parse, per {@see ImporterInterface}'s framework-free contract --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class BankOfIsraelImporter implements ImporterInterface
{
    public function countryCode(): string
    {
        return 'IL';
    }

    public function sourceId(): string
    {
        return 'boi';
    }

    public function sourceName(): string
    {
        return 'Bank of Israel (data.gov.il)';
    }

    public function license(): string
    {
        return 'Bank of Israel (data.gov.il, other-open)';
    }

    public function sourceUrl(): string
    {
        return 'https://data.gov.il/api/3/action/datastore_search?resource_id=6f3bda2a-8cde-4b86-a1c8-2761862b1224&limit=100000';
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

        $result = $data['result'] ?? null;

        if (! is_array($result)) {
            return;
        }

        $records = $result['records'] ?? null;

        if (! is_array($records)) {
            return;
        }

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($records as $record) {
            if (! is_array($record)) {
                continue;
            }

            $rawBankCode = $record['Bank_Code'] ?? null;

            if (is_int($rawBankCode)) {
                $bankCode = str_pad((string) $rawBankCode, 3, '0', STR_PAD_LEFT);
            } elseif (is_string($rawBankCode) && $rawBankCode !== '') {
                $bankCode = str_pad((string) (int) $rawBankCode, 3, '0', STR_PAD_LEFT);
            } else {
                continue;
            }

            if (isset($seen[$bankCode])) {
                continue; // another branch of the same bank, already seeded
            }

            $seen[$bankCode] = true;

            $name = $record['Bank_Name'] ?? null;

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
