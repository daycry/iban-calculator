<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\NormalizesStrings;

/**
 * Official-source importer for France (FR) and Monaco (MC): the REGAFI
 * register (ACPR / Banque de France), published openly through an
 * Opendatasoft Explore v2.1 backend. Maps the 5-digit CIB (code
 * interbancaire -- the first 5 digits of a French/Monégasque BBAN) to the
 * institution's name.
 *
 * Like {@see EpcRegisterImporter}, this class is **parameterized per country**
 * via its constructor and registered twice -- `new RegafiImporter('FR')` and
 * `new RegafiImporter('MC')`. Monaco's institutions sit in the SAME REGAFI
 * dataset and carry a CIB (e.g. CFM Indosuez `12739`, Crédit mobilier de
 * Monaco `10160`), so one source resolves both countries; the two instances
 * differ only in the `pays` filter they apply.
 *
 * - Live source: {@see self::sourceUrl()} -- the `prd-banque-entites`
 *   dataset's `exports/json` endpoint, a flat JSON array of records, no
 *   authentication.
 * - **`cib` is a JSON-serialized array, not a scalar** (confirmed in
 *   `research.md` §5): `"[\"24659\"]"` (array of code strings) or
 *   `"[{\"code\":\"15208\",\"date\":\"...\"}]"` (array of `{code,...}`
 *   objects), or `"[]"`/empty when the entity has none. A single entity can
 *   carry several CIBs. {@see self::rows()} decodes it and **expands one row
 *   per code**, so a two-CIB entity yields two `banks` rows. Each code is
 *   zero-padded to 5 digits (a source value that dropped a leading zero is
 *   recovered) and validated as 1-5 digits; anything else is skipped.
 * - `name` = the `denomination` field. REGAFI carries **no BIC** -- the `bic`
 *   key is left unset (a BIC enrichment layer via the EPC register is
 *   deferred, see the initiative spec's D5), valid per
 *   {@see ImporterInterface::rows()}'s optional-`bic` contract.
 * - **Country filter**: a record is kept only when one of its country fields
 *   ({@see self::COUNTRY_FIELDS}) matches this instance's country
 *   ({@see self::COUNTRY_TOKENS}). Confirmed live 2026-07-16: the dataset
 *   exposes the country in the `pays` field as the full uppercase name
 *   `FRANCE` / `MONACO` (Monaco entities sit in the same dataset and carry a
 *   CIB, e.g. `11999`, `10160`). A record with no recognizable country field
 *   is treated as "not this country" (skipped) rather than defaulted, so
 *   France and Monaco never bleed into each other.
 * - **Dedup by `bank_code`**: first name seen for a CIB wins, defensive
 *   against the same code appearing under more than one record.
 *
 * LICENSING: REGAFI is published under the French Licence Ouverte / Etalab
 * (reuse permitted with attribution to the source and its date).
 *
 * CAVEAT: parsing targets the documented/observed REGAFI export shape as of
 * this release (notably the `cib` serialization and the country field name)
 * -- validate against the live endpoint before production use; the portal can
 * change field names/layout without notice.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `json_decode()`)
 * to fetch/parse, per {@see ImporterInterface}'s framework-free contract --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 */
final class RegafiImporter implements ImporterInterface
{
    use NormalizesStrings;

    /** @var array<string, list<string>> ISO2 -> the country-field values that mark a record as this country. */
    private const COUNTRY_TOKENS = [
        'FR' => ['FR', 'FRANCE'],
        'MC' => ['MC', 'MONACO'],
    ];

    /** @var list<string> Candidate record fields carrying the entity's country, in priority order. */
    private const COUNTRY_FIELDS = ['pays', 'code_pays', 'pays_localisation', 'localisation'];

    private const CIB_PATTERN = '/^[0-9]{1,5}$/';

    public function __construct(private string $country)
    {
    }

    public function countryCode(): string
    {
        return strtoupper($this->country);
    }

    public function sourceId(): string
    {
        return 'regafi';
    }

    public function sourceName(): string
    {
        return 'REGAFI (ACPR / Banque de France)';
    }

    public function license(): string
    {
        return 'Licence Ouverte / Etalab (attribution)';
    }

    public function sourceUrl(): string
    {
        return 'https://regafi.fr/api/explore/v2.1/catalog/datasets/prd-banque-entites/exports/json';
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

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return;
        }

        $records = self::extractRecords($decoded);

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($records as $record) {
            if (! is_array($record) || ! $this->recordMatchesCountry($record)) {
                continue;
            }

            $name = $record['denomination'] ?? null;
            $name = is_string($name) ? self::nullableTrim($name) : null;

            foreach (self::extractCibCodes($record['cib'] ?? null) as $code) {
                if (isset($seen[$code])) {
                    continue; // same CIB already emitted
                }

                $seen[$code] = true;

                yield [
                    'bank_code'   => $code,
                    'branch_code' => null,
                    'name'        => $name,
                ];
            }
        }
    }

    /**
     * Resolves the array of records from either a plain top-level list (the
     * `exports/json` shape) or an Explore-API envelope (`results`/`records`).
     *
     * @param array<mixed> $decoded
     *
     * @return array<mixed>
     */
    private static function extractRecords(array $decoded): array
    {
        if (array_is_list($decoded)) {
            return $decoded;
        }

        foreach (['results', 'records'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                return $decoded[$key];
            }
        }

        return [];
    }

    /**
     * `true` when the first present country field on `$record` carries a value
     * matching this instance's country tokens.
     *
     * @param array<string, mixed> $record
     */
    private function recordMatchesCountry(array $record): bool
    {
        $tokens = self::COUNTRY_TOKENS[$this->countryCode()] ?? [$this->countryCode()];

        foreach (self::COUNTRY_FIELDS as $field) {
            if (! isset($record[$field]) || ! is_scalar($record[$field])) {
                continue;
            }

            $value = strtoupper(trim((string) $record[$field]));

            if ($value === '') {
                continue;
            }

            return in_array($value, $tokens, true);
        }

        return false;
    }

    /**
     * Decodes REGAFI's serialized `cib` value into a list of zero-padded,
     * validated 5-digit CIB codes.
     *
     * @return list<string>
     */
    private static function extractCibCodes(mixed $cib): array
    {
        if (is_string($cib)) {
            $trimmed = trim($cib);
            $cib     = $trimmed === '' ? [] : json_decode($trimmed, true);
        }

        if (! is_array($cib)) {
            return [];
        }

        $codes = [];

        foreach ($cib as $entry) {
            if (is_array($entry)) {
                $entry = $entry['code'] ?? null;
            }

            if (is_int($entry)) {
                $entry = (string) $entry;
            }

            if (! is_string($entry)) {
                continue;
            }

            $entry = trim($entry);

            if (preg_match(self::CIB_PATTERN, $entry) !== 1) {
                continue;
            }

            $codes[] = str_pad($entry, 5, '0', STR_PAD_LEFT);
        }

        return $codes;
    }
}
