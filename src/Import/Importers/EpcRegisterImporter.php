<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use DateTimeImmutable;
use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\ReadsCsvSource;

/**
 * Supranational importer for the European Payments Council's (EPC) SEPA
 * Register: the downloadable, per-scheme CSV export of every participant
 * reachable under SCT / SCT Inst / SDD Core / SDD B2B.
 *
 * Unlike every other bundled importer (one class per issuing country), this
 * one is **parameterized per country** via its constructor -- the same class
 * is instantiated once per target country ({@see ImporterRegistry::registerDefaults()}
 * registers five instances: GB, GI, IE, LV, RO). This is the set of SEPA
 * countries whose IBAN `bank_code` is exactly the BIC's 4-letter institution
 * prefix (`substr(BIC, 0, 4)`, confirmed for IE -> AIBK, RO -> BTRL,
 * LV -> HABA, GB -> SRLG/BARC/LOYD, GI -> XAPO) and that have NO dedicated
 * national importer in this package -- BG/MT/NL share the same bank-code
 * shape but already have one ({@see BulgarianNationalBankImporter},
 * {@see CentralBankOfMaltaImporter}, {@see BetaalverenigingImporter}), so
 * they are deliberately not also registered here (the registry keys by
 * `(country, sourceId)`, so double-registration would simply be redundant,
 * not harmful, but there is no benefit to it).
 *
 * - Live source: one CSV per scheme, all at the same shape:
 *   `https://www.europeanpaymentscouncil.eu/sites/default/files/participants_export/{scheme}/{scheme}.csv`
 *   for `scheme` in `sct`, `sct_inst`, `sdd_core`, `sdd_b2b`.
 *   {@see self::sourceUrl()} points at the `sct` file, the primary/reference
 *   scheme this importer's offline (single-file) mode consumes.
 * - Format (confirmed live 2026-07-11 against the real `sct.csv`): COMMA
 *   delimited, every field double-quoted, UTF-8, NO leading BOM. Header:
 *   `Country,ParticipantName,Address,City,BIC,Readiness Date,Scheme Leaving
 *   Date,Scheme Options`. Columns are matched by NAME (via the header row),
 *   not position, same rationale as {@see OenbImporter}.
 * - **`Country` is the participant's country as a FULL, ALL-CAPS ENGLISH
 *   NAME** (e.g. `"UNITED KINGDOM"`, `"IRELAND"`, `"GIBRALTAR"`), NOT an ISO
 *   3166-1 alpha-2 code -- confirmed live for all five target countries.
 *   {@see self::EPC_COUNTRY_NAMES} maps each supported ISO2 code to the
 *   exact EPC name it must match; this map only covers the five countries
 *   this importer is meant to be instantiated for; extending it to another
 *   EPC-listed country requires adding that country's EPC name here too.
 *   Note the live file's `Country` grouping follows the participant's own
 *   registration, not the BIC's country letters -- e.g. a Jersey-domiciled
 *   subsidiary of a UK bank can appear under `"UNITED KINGDOM"` with a `JE`
 *   BIC country code; that participant is still included here because this
 *   importer filters on the `Country` COLUMN, matching the live registry's
 *   own grouping.
 * - `bank_code` = `strtoupper(substr(trim(BIC), 0, 4))`. Rows with a BIC
 *   shorter than 4 characters (including empty) are skipped entirely --
 *   there is no institution prefix to key a `banks` row on.
 * - **Bank-level dedup**: several BICs can share the same 4-letter prefix
 *   (e.g. a UK bank's Jersey-booking entity alongside its GB entity, or a
 *   Gibraltar branch of a UK-chartered bank) -- {@see self::rows()} yields
 *   only the FIRST row seen for each `bank_code`, silently skipping the
 *   rest, same rule as {@see BulgarianNationalBankImporter} /
 *   {@see CentralBankOfMaltaImporter}.
 * - **Leaving-date filter (simplification)**: a participant whose `Scheme
 *   Leaving Date` is a non-empty, well-formed (`YYYY-MM-DD`) date STRICTLY
 *   BEFORE today is skipped as no longer current. A blank leaving date, or
 *   one that isn't a clean `YYYY-MM-DD` string, is treated as "still
 *   current" rather than attempting a more elaborate determination -- the
 *   Register is a live export with no historical/point-in-time query, so
 *   "is this row still in the file" is already a reasonable proxy for
 *   "current" and this filter only tightens it for the unambiguous case of
 *   an already-past, explicitly-dated exit.
 * - Encoding: defensively strips a leading UTF-8 BOM even though the live
 *   file doesn't carry one, same defensive posture as
 *   {@see BancoDeEspanaImporter}.
 *
 * ## Offline vs. live shape
 *
 * - **Offline** (`$localFile !== null`): parses that ONE file as an EPC
 *   `sct`-shaped CSV. Every yielded row sets `sepa_sct: true` and leaves the
 *   other three SEPA scheme flags (`sepa_sct_inst`, `sepa_sdd_core`,
 *   `sepa_sdd_b2b`) unset (`null` once {@see \Daycry\Iban\Import\ImportRunner}
 *   maps them) -- offline import only ever sees the one file it was handed.
 * - **Live** (`$localFile === null`, NOT exercised by this package's test
 *   suite, same as every other importer's live path -- opt-in, network
 *   fetch only): fetches all FOUR scheme files, builds a per-`bank_code`
 *   reachability map, and yields one row per `bank_code` reachable in AT
 *   LEAST one successfully-fetched scheme for {@see self::countryCode()},
 *   with each `sepa_*` flag set to `true`/`false` per that scheme's own
 *   file, or left `null` if that scheme's file could not be fetched/parsed
 *   at all (rather than guessing `false`, which would misreport "not
 *   reachable" as opposed to "unknown"). If every scheme file fails,
 *   nothing is yielded.
 *
 * LICENSING: the EPC permits viewing, downloading and reusing SEPA Register
 * data when it is enriched into a broader dataset (this package's exact
 * use), subject to crediting the EPC and not reselling the data as-is.
 *
 * CAVEAT: parsing targets the documented/observed source format as of this
 * release -- validate against the live official files before production
 * use; the EPC could change the layout without notice.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`)
 * to fetch/parse, per {@see ImporterInterface}'s framework-free contract --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class EpcRegisterImporter implements ImporterInterface
{
    use ReadsCsvSource;

    /** @var array<string, string> ISO2 -> the exact EPC `Country` column value it must match. */
    private const EPC_COUNTRY_NAMES = [
        'GB' => 'UNITED KINGDOM',
        'GI' => 'GIBRALTAR',
        'IE' => 'IRELAND',
        'LV' => 'LATVIA',
        'RO' => 'ROMANIA',
    ];

    /** @var array<string, string> SEPA scheme flag key -> its EPC participants-export CSV URL. */
    private const SCHEME_URLS = [
        'sepa_sct'      => 'https://www.europeanpaymentscouncil.eu/sites/default/files/participants_export/sct/sct.csv',
        'sepa_sct_inst' => 'https://www.europeanpaymentscouncil.eu/sites/default/files/participants_export/sct_inst/sct_inst.csv',
        'sepa_sdd_core' => 'https://www.europeanpaymentscouncil.eu/sites/default/files/participants_export/sdd_core/sdd_core.csv',
        'sepa_sdd_b2b'  => 'https://www.europeanpaymentscouncil.eu/sites/default/files/participants_export/sdd_b2b/sdd_b2b.csv',
    ];

    private const COLUMN_COUNTRY      = 'Country';
    private const COLUMN_NAME         = 'ParticipantName';
    private const COLUMN_ADDRESS      = 'Address';
    private const COLUMN_CITY         = 'City';
    private const COLUMN_BIC          = 'BIC';
    private const COLUMN_LEAVING_DATE = 'Scheme Leaving Date';

    public function __construct(private string $country)
    {
    }

    public function countryCode(): string
    {
        return strtoupper($this->country);
    }

    public function sourceId(): string
    {
        return 'epc';
    }

    public function sourceName(): string
    {
        return 'European Payments Council (SEPA Register)';
    }

    public function license(): string
    {
        return 'EPC SEPA Register (credit EPC, no resale as-is)';
    }

    public function sourceUrl(): string
    {
        return self::SCHEME_URLS['sepa_sct'];
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        if ($localFile !== null) {
            yield from $this->offlineRows($localFile);

            return;
        }

        yield from $this->liveRows();
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function offlineRows(string $localFile): iterable
    {
        $raw = @file_get_contents($localFile);

        if ($raw === false || $raw === '') {
            return;
        }

        $records = $this->extractCountryRecords($raw, $this->epcCountryName());

        foreach ($records as $bankCode => $record) {
            yield [
                'bank_code'   => $bankCode,
                'branch_code' => null,
                'name'        => $record['name'],
                'bic'         => $record['bic'],
                'city'        => $record['city'],
                'address'     => $record['address'],
                'sepa_sct'    => true,
            ];
        }
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    private function liveRows(): iterable
    {
        $countryName = $this->epcCountryName();

        /** @var array<string, array<string, array{name: ?string, bic: string, city: ?string, address: ?string}>> $recordsByScheme keyed by scheme flag, then by bank_code; empty (not missing) for a scheme that failed to fetch. */
        $recordsByScheme = [];

        /** @var array<string, bool> $fetchedByScheme whether each scheme flag's file was successfully fetched/parsed at all. */
        $fetchedByScheme = [];

        foreach (self::SCHEME_URLS as $schemeFlag => $url) {
            $raw = @file_get_contents($url);

            if ($raw === false || $raw === '') {
                $fetchedByScheme[$schemeFlag] = false;
                $recordsByScheme[$schemeFlag] = [];

                continue;
            }

            $fetchedByScheme[$schemeFlag] = true;
            $recordsByScheme[$schemeFlag]  = $this->extractCountryRecords($raw, $countryName);
        }

        /** @var array<string, true> $bankCodes union of every bank_code reachable in at least one scheme. */
        $bankCodes = [];

        foreach ($recordsByScheme as $records) {
            foreach (array_keys($records) as $bankCode) {
                $bankCodes[$bankCode] = true;
            }
        }

        foreach (array_keys($bankCodes) as $bankCode) {
            $representative = null;

            foreach (self::SCHEME_URLS as $schemeFlag => $url) {
                if (isset($recordsByScheme[$schemeFlag][$bankCode])) {
                    $representative = $recordsByScheme[$schemeFlag][$bankCode];

                    break;
                }
            }

            if ($representative === null) {
                continue; // defensive only: $bankCode always came from $recordsByScheme itself
            }

            $row = [
                'bank_code'   => $bankCode,
                'branch_code' => null,
                'name'        => $representative['name'],
                'bic'         => $representative['bic'],
                'city'        => $representative['city'],
                'address'     => $representative['address'],
            ];

            foreach (self::SCHEME_URLS as $schemeFlag => $url) {
                $row[$schemeFlag] = $fetchedByScheme[$schemeFlag] ? isset($recordsByScheme[$schemeFlag][$bankCode]) : null;
            }

            yield $row;
        }
    }

    private function epcCountryName(): string
    {
        return self::EPC_COUNTRY_NAMES[$this->countryCode()] ?? $this->countryCode();
    }

    /**
     * Parses one EPC scheme-export CSV, filters it down to `$countryName`,
     * skips rows with no usable BIC or a resolvable past `Scheme Leaving
     * Date`, and dedups by `bank_code` (first occurrence wins).
     *
     * @return array<string, array{name: ?string, bic: string, city: ?string, address: ?string}> keyed by `bank_code`, in first-seen order.
     */
    private function extractCountryRecords(string $raw, string $countryName): array
    {
        /** @var list<string>|null $header */
        $header = null;

        /** @var array<string, array{name: ?string, bic: string, city: ?string, address: ?string}> $records */
        $records = [];

        foreach ($this->parseCsvBytes($raw, ',') as $fields) {
            if ($header === null) {
                if ($fields === [null]) {
                    return [];
                }

                $header = array_map(static fn (?string $column): string => trim($column ?? ''), $fields);

                continue;
            }

            if ($fields === [null]) {
                continue; // blank line
            }

            if (count($fields) !== count($header)) {
                continue; // malformed row: column count doesn't match the header
            }

            /** @var list<string> $fields */
            $fields = array_map(static fn (?string $value): string => $value ?? '', $fields);

            /** @var array<string, string> $row */
            $row = array_combine($header, $fields);

            if (strtoupper(trim($row[self::COLUMN_COUNTRY] ?? '')) !== $countryName) {
                continue;
            }

            $bic = trim($row[self::COLUMN_BIC] ?? '');

            if (strlen($bic) < 4) {
                continue; // no usable institution prefix
            }

            $bankCode = strtoupper(substr($bic, 0, 4));

            if (isset($records[$bankCode])) {
                continue; // dedup: another BIC already established this bank_code
            }

            if (self::isPastLeavingDate($row[self::COLUMN_LEAVING_DATE] ?? '')) {
                continue; // no longer a current scheme participant
            }

            $records[$bankCode] = [
                'name'    => self::nullableTrim($row[self::COLUMN_NAME] ?? ''),
                'bic'     => $bic,
                'city'    => self::nullableTrim($row[self::COLUMN_CITY] ?? ''),
                'address' => self::nullableTrim($row[self::COLUMN_ADDRESS] ?? ''),
            ];
        }

        return $records;
    }

    /**
     * `true` only when `$value` is a well-formed `YYYY-MM-DD` date strictly
     * before today -- a blank value or one that doesn't parse cleanly is
     * treated as "still current" (see the class docblock's leaving-date
     * simplification note) rather than skipped.
     */
    private static function isPastLeavingDate(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $leavingDate = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($leavingDate === false) {
            return false;
        }

        return $leavingDate < new DateTimeImmutable('today');
    }
}
