# Bank-data importers

Reference for the importer subsystem: the `ImporterInterface` contract, the `iban:update` command, the
30 bundled official-source importers, the bank-level resolution model, the EPC SEPA Register importer,
how provenance is stored, and how to write a custom importer. See [`docs/licensing.md`](licensing.md)
first for *why* this package ships zero bundled bank data and imports everything on-demand instead.

- [Overview](#overview)
- [`iban:update` usage](#ibanupdate-usage)
- [The 30 bundled importers](#the-30-bundled-importers)
- [Coverage matrix](#coverage-matrix)
- [Bank-level resolution: why `branch_code` is `null`](#bank-level-resolution-why-branch_code-is-null)
- [The EPC SEPA Register importer](#the-epc-sepa-register-importer)
- [`.xlsx` sources and offline-only imports](#xlsx-sources-and-offline-only-imports)
- [Provenance: how imported data is stored](#provenance-how-imported-data-is-stored)
- [Writing a custom importer](#writing-a-custom-importer)

## Overview

Four pieces make up the importer subsystem:

| Piece | Location | Framework-free? | Role |
|---|---|---|---|
| `ImporterInterface` | `src/Contracts/ImporterInterface.php` | Yes | Per-`(country, source)` contract: describes the source and yields normalized rows. |
| `ImportReport` | `src/Import/ImportReport.php` | Yes | Immutable result of one import run: `fetched`/`imported`/`skipped`/`dryRun`/`messages`. |
| `ImporterRegistry` | `src/Import/ImporterRegistry.php` | Yes | In-memory catalog of `ImporterInterface` instances, keyed by `(countryCode, sourceId)`. `registerDefaults()` wires in all 30 bundled importers. |
| `ImportRunner` | `src/Import/ImportRunner.php` | **No** (uses `Models\BankModel`) | Runs one importer's `rows()` against the `banks` table, upserting by natural key and stamping provenance. |
| `UpdateCommand` | `src/Commands/UpdateCommand.php` | No (CI4 `spark` command) | `iban:update` — lists/selects/runs importers, prints an `ImportReport`. |

`ImporterInterface` and `ImportReport` live under `src/Import/`, which is **not** one of the
directories guarded by `tests/Architecture/CoreIsFrameworkFreeTest.php` — but both are written to the
same framework-free discipline as the guarded core anyway, so the contract stays usable outside CI4.
`ImportRunner` is the one piece in this subsystem that genuinely needs CI4 (it constructs
`Models\BankModel`), which is why it sits in `src/Import/` rather than a guarded directory.

```php
public function countryCode(): string;              // ISO 3166-1 alpha-2, e.g. 'AT'
public function sourceId(): string;                  // stable id, e.g. 'oenb' — stored in banks.source_id
public function sourceName(): string;                // human-readable publisher name
public function license(): string;                   // license/attribution string — stored in banks.source_license
public function sourceUrl(): string;                 // official download URL (docs + default live-fetch location)
public function rows(?string $localFile = null): iterable; // yields normalized bank rows
```

`rows()` yields associative arrays with these recognized keys (all but `bank_code` optional):
`bank_code`, `branch_code`, `bic`, `name`, `short_name`, `city`, `address`, `sepa_sct`,
`sepa_sct_inst`, `sepa_sdd_core`, `sepa_sdd_b2b`. When `$localFile` is given, the importer **must**
parse that file instead of reaching the network (offline import); when it's `null`, it fetches live
from `sourceUrl()`. Nearly every bundled importer yields `branch_code: null` — see
[Bank-level resolution](#bank-level-resolution-why-branch_code-is-null) for why, and how a
branch-carrying IBAN still resolves against those rows.

## `iban:update` usage

```
iban:update [--country=<cc>] [--source=<id>] [--dry-run] [--file=<path>]
```

| Flag | Meaning |
|---|---|
| `--country=<cc>` | Restrict to importers for this ISO 3166-1 alpha-2 country code (e.g. `AT`). |
| `--source=<id>` | Restrict to the importer with this source id (e.g. `oenb`). |
| `--dry-run` | Preview: count what would be imported/skipped without writing to the `banks` table. |
| `--file=<path>` | Import offline from this local file instead of fetching from the source live. |

v1.0's licensing notices (SWIFT IBAN Registry / SWIFT BIC Directory / per-source attribution) are
always printed first, regardless of selection. With **no** `--country`/`--source` at all, the command
only lists the registered importers — nothing runs:

```bash
$ php spark iban:update
SWIFT IBAN Registry is non-commercial/no-derivatives (not bundled).
SWIFT BIC Directory (SwiftRef) is proprietary (not bundled).
National lists require per-source attribution.
Registered importers: 30
+---------+------------------+-------------------------------+------------------------------------+
| Country | Source           | Name                          | License                             |
+---------+------------------+-------------------------------+------------------------------------+
| AT      | oenb             | Oesterreichische Nationalbank | CC-BY-4.0 (OeNB)                    |
| DE      | bundesbank       | Deutsche Bundesbank           | Deutsche Bundesbank                 |
| CH      | six              | SIX Interbank Clearing        | SIX Interbank Clearing (free use)   |
| NL      | betaalvereniging | Betaalvereniging Nederland    | Betaalvereniging Nederland (see terms) |
| ES      | bde              | Banco de España               | Banco de España                     |
| CZ      | cnb              | Czech National Bank           | Czech National Bank (cite source, no changes) |
| ...     | ...              | ... (22 more)                  | ...                                  |
| GB/GI/IE/LV/RO | epc       | European Payments Council (SEPA Register) | EPC SEPA Register (credit EPC, no resale as-is) |
+---------+------------------+-------------------------------+------------------------------------+
Select one with --country=/--source= to run it (add --dry-run to preview).
```

(The full 30-row listing is in [the table below](#the-30-bundled-importers) — the block above is
abbreviated for readability.) Selecting a source (by `--country`, `--source`, or both) runs each match
through `ImportRunner` against `Config\Iban::$table`/`$dbGroup`, and prints its `ImportReport`:

```bash
# Live fetch (OeNB and Bundesbank both fetch from their sourceUrl() over the network)
$ php spark iban:update --source=oenb
[AT/oenb] fetched=<N> imported=<N> skipped=0
Source: Oesterreichische Nationalbank — https://www.oenb.at/docroot/downloads_observ/bankstellenverzeichnis.csv (CC-BY-4.0 (OeNB))

$ php spark iban:update --source=bundesbank
[DE/bundesbank] fetched=<N> imported=<N> skipped=0
Source: Deutsche Bundesbank — https://www.bundesbank.de/resource/blob/602632/.../blz-aktuell-txt-data.txt (Deutsche Bundesbank)

# Dry run: preview counts, write nothing
$ php spark iban:update --country=AT --dry-run
[AT/oenb] fetched=<N> imported=<N> skipped=0 (dry-run — nothing written)
Source: Oesterreichische Nationalbank — https://www.oenb.at/docroot/downloads_observ/bankstellenverzeichnis.csv (CC-BY-4.0 (OeNB))

# Offline import from a file you already downloaded/exported
$ php spark iban:update --source=six --file=/path/to/bankmaster_V3.csv
[CH/six] fetched=... imported=... skipped=...
Source: SIX Interbank Clearing — https://api.six-group.com/api/epcd/bankmaster/v3/bankmaster_V3.csv (SIX Interbank Clearing (free use))

$ php spark iban:update --source=betaalvereniging --country=NL --file=/path/to/BIC-lijst-NL-export.csv
[NL/betaalvereniging] fetched=... imported=... skipped=...
Source: Betaalvereniging Nederland — https://www.betaalvereniging.nl/wp-content/uploads/BIC-lijst-NL.xlsx (Betaalvereniging Nederland (see terms))

$ php spark iban:update --source=bde --file=/path/to/lista-mfi-es.csv
[ES/bde] fetched=... imported=... skipped=...
Source: Banco de España — https://www.bde.es/webbe/es/estadisticas/.../lista-mfi-es.csv (Banco de España)

# EPC SEPA Register: --country selects which registered country instance runs
$ php spark iban:update --country=IE --source=epc --file=/path/to/sct.csv
[IE/epc] fetched=... imported=... skipped=...
Source: European Payments Council (SEPA Register) — https://www.europeanpaymentscouncil.eu/sites/default/files/participants_export/sct/sct.csv (EPC SEPA Register (credit EPC, no resale as-is))
```

(`fetched`/`imported`/`skipped` counts above are illustrative — `<N>`/`...` stand in for whatever the
live source or the file you point `--file` at actually contains at run time; `fetched === imported`
only when nothing was skipped.)

A selection matching no registered importer prints `No bundled importer matches that selection.` and
exits `0` (no exception).

## The 30 bundled importers

None of these ship any actual data in the repository — running them is always something the operator
does deliberately, against a live network fetch or a locally downloaded/exported file. `--source=` is
shared by `LI` and `CH` (both `six`, the same SIX Bank Master V3 file, filtered by country) and by the
five EPC registrations (all `epc`, disambiguated by `--country=`).

| Country | `--source=` | Publisher | Format | License |
|---|---|---|---|---|
| AT | `oenb` | Oesterreichische Nationalbank | `;`-CSV, UTF-8/Windows-1252 fallback | CC-BY-4.0 (OeNB) |
| DE | `bundesbank` | Deutsche Bundesbank | Fixed-width ASCII (168 chars/record), ISO-8859-1 | Deutsche Bundesbank (free use, mandatory attribution) |
| CH | `six` | SIX Interbank Clearing | `;`-CSV (Bank Master V3), UTF-8 | SIX Interbank Clearing (free use) |
| NL | `betaalvereniging` | Betaalvereniging Nederland | `;`-CSV export of the source `.xlsx`, UTF-8 | Betaalvereniging Nederland (see terms — written consent needed to redistribute) |
| ES | `bde` | Banco de España | `,`-CSV, UTF-8 with leading BOM | Banco de España (attribution + no alteration) |
| CZ | `cnb` | Czech National Bank | `;`-CSV, UTF-8 with leading BOM | Czech National Bank (cite source, no changes) |
| GR | `hba` | Hellenic Bank Association (HEBIC) | `;`-CSV, Windows-1253 | Hellenic Bank Association (HEBIC) — no explicit license stated |
| SI | `bsi` | Bank of Slovenia | `;`-CSV, Windows-1250 | Bank of Slovenia (cite source, no changes) |
| SK | `nbs` | National Bank of Slovakia | `;`-CSV, Windows-1250 | National Bank of Slovakia |
| BG | `bnb` | Bulgarian National Bank | SpreadsheetML XML (`.xls` in name only), UTF-8 with BOM | Bulgarian National Bank |
| MD | `bnm` | National Bank of Moldova | XML (`latin1`-declared, decoded natively by libxml) | National Bank of Moldova |
| PL | `nbp` | Narodowy Bank Polski (EWIB) | XML, UTF-8 | Narodowy Bank Polski (public sector information, free reuse) |
| AZ | `cbar` | Central Bank of Azerbaijan | XML, UTF-8 | Central Bank of Azerbaijan — no explicit license stated |
| BE | `nbb` | National Bank of Belgium | `.xlsx` (via `XlsxReader`) | National Bank of Belgium |
| HR | `hnb` | Croatian National Bank | `.xlsx` (via `XlsxReader`) | Croatian National Bank (cite source, no changes) |
| LU | `abbl` | ABBL (Luxembourg Register of IBAN/BIC) | `.xlsx` (via `XlsxReader`), offline (`--file`) only — rotating download URL | ABBL Luxembourg IBAN/BIC Register |
| MT | `cbm` | Central Bank of Malta | `.xlsx` (via `XlsxReader`) | Central Bank of Malta |
| HU | `mnb` | Magyar Nemzeti Bank | `.xlsx` (via `XlsxReader`) | Magyar Nemzeti Bank |
| NO | `bits` | Bits AS (Norway) | `.xlsx` (via `XlsxReader`), landing page — verify a live fetch still works | Bits AS (Norway) |
| GE | `nbg` | National Bank of Georgia | `.xlsx` (via `XlsxReader`), landing page with a date-stamped link — offline recommended | National Bank of Georgia |
| IL | `boi` | Bank of Israel (data.gov.il) | JSON (CKAN `datastore_search`) | Bank of Israel (data.gov.il, other-open) |
| UA | `nbu` | National Bank of Ukraine | JSON, portal blocks automated fetches — offline (`--file`) is the tested path | National Bank of Ukraine (open data) |
| KZ | `nbk` | National Bank of Kazakhstan | JSON, portal requires an API key this package doesn't ship — offline (`--file`) is the tested path | National Bank of Kazakhstan (open data) |
| LI | `six` | SIX Interbank Clearing (Liechtenstein) | `;`-CSV, same Bank Master V3 file as CH, filtered `Country === 'LI'` | SIX Interbank Clearing (free use) |
| BR | `bcb` | Banco Central do Brasil | `,`-CSV, UTF-8 with leading BOM | Banco Central do Brasil (ODbL) |
| GB | `epc` | European Payments Council (SEPA Register) | `,`-CSV, one file per SEPA scheme | EPC SEPA Register (credit EPC, no resale as-is) |
| GI | `epc` | European Payments Council (SEPA Register) | `,`-CSV, one file per SEPA scheme | EPC SEPA Register (credit EPC, no resale as-is) |
| IE | `epc` | European Payments Council (SEPA Register) | `,`-CSV, one file per SEPA scheme | EPC SEPA Register (credit EPC, no resale as-is) |
| LV | `epc` | European Payments Council (SEPA Register) | `,`-CSV, one file per SEPA scheme | EPC SEPA Register (credit EPC, no resale as-is) |
| RO | `epc` | European Payments Council (SEPA Register) | `,`-CSV, one file per SEPA scheme | EPC SEPA Register (credit EPC, no resale as-is) |

Each importer's class docblock (`src/Import/Importers/*.php`) documents its exact column
layout/positions, encoding quirks, and any row-filtering/dedup rules (e.g. OeNB and Bundesbank dedupe
per-branch rows down to one head-office record per bank code; several XML/JSON sources dedupe several
BICs/settlement codes sharing the same institution prefix). Read the relevant docblock before running
an importer against a freshly downloaded file — publishers change layouts without notice, and every
docblock says so explicitly ("CAVEAT: parsing targets the documented/observed format as of this
release").

## Coverage matrix

30 of the 78 registry countries now have a bundled importer. Of the 42 SEPA-scheme countries, **24 now
resolve** through a bundled importer (the 23 non-EPC SEPA countries below, plus GB/GI/IE/LV/RO via the
EPC SEPA Register).

**Covered (30)** — grouped by source shape:

| Group | Countries |
|---|---|
| v1.1, CSV/fixed-width | AT, DE, CH, NL, ES |
| v1.2, CSV | CZ, GR, SI, SK, BR |
| v1.2, XML | BG, MD, PL, AZ |
| v1.2, `.xlsx` (`XlsxReader`) | BE, HR, LU, MT, HU, NO, GE |
| v1.2, JSON | IL, UA, KZ |
| v1.2, shared SIX source | LI (alongside CH) |
| v1.2, EPC SEPA Register | GB, GI, IE, LV, RO |

**Deferred** — a plausible source exists but was deliberately not shipped:

| Country | Why |
|---|---|
| FI | Finance Finland's `rahalaitostunnus` is only 1–3 digits, and cannot be zero-padded into the 3-digit IBAN `bank_code` the registry parses without risking a wrong mapping; the machine-readable file is also stale (dated 2022, while the PDF is refreshed) and its license is unstated. FI keeps its v1.1 `FinnishNationalCheckValidator` (Luhn check-digit validation) instead of a bank-directory importer. |
| BY | A viable-looking source exists (NBRB's `bic-rb.xlsx`), but shipping it raises a sanctions/compliance concern; the site also blocked first-hand format verification, its license is unstated, and the bank-code mapping would be a BIC-prefix derivation, not an explicit column. Documented, not built. |

**Not viable** — no clean, open, machine-readable, IBAN-bank-code-mapping source was found (buckets
below; full per-country reasoning is in the v1.2 research record):

| Bucket | Countries |
|---|---|
| Paywalled | FR, IT (GB's EISCD was also paywalled — GB is now covered via the EPC SEPA Register instead) |
| PDF-only | PT, SE, DK, LT, CY, RS, EG, LB, MC, MU, PK, QA, SC, TN, LC |
| Portal/HTML-only, no bulk export | AL, BA, ME, MK, EE, SA, SV, TL, KW, JO, SM, BH |
| SWIFT-only (`bank_code` = BIC prefix, non-SEPA so not in the EPC register) | DO, VG, IQ, ST, LY, MR, AE, CR, GT, XK, VA, AD |
| Other (inherit another country's blocker, no directory at all, or fail on licensing/freshness) | FO and GL (use Denmark's `registreringsnummer` system — see DK, PDF-only), IS (no official directory of any kind; SWIFT-only in practice), PS (PMA's directory is address-only, currently unreachable, and PS's `bank_code` is itself SWIFT-derived), TR (the only downloadable file is a stale one-off 2022 export with no maintained refresh path or stated license) |

IE, LV, RO, GI and GB were previously bucketed as SWIFT-only/portal-only/paywalled in the pre-EPC
research pass — they moved to "covered" once the EPC SEPA Register importer shipped, since the SEPA
Register publishes exactly the BIC each of their IBANs' `bank_code` is derived from.

## Bank-level resolution: why `branch_code` is `null`

Almost every bundled importer yields `branch_code: null` even for countries whose IBAN structurally
carries a branch segment (ES, GR, HU, MT, PL, and others) — because nearly every official source
publishes bank-level data (one row per institution, or per settlement/clearing code that this package
rolls up to bank level), not a full per-branch directory. Seeding `branch_code = null` is deliberate,
not a shortcut: `banks` is keyed on `(country_code, bank_code, branch_code)`, so an exact `branch_code`
match would otherwise be required for `resolve()` to find anything at all, and most operators will
never have a matching branch-level row.

`Resolver::resolve()` (`src/Resolver/Resolver.php`) handles this with a two-step lookup: it tries
`ProviderInterface::findByIban()` first (an exact match on the parsed IBAN's own bank *and* branch); if
that returns `null`, it falls back to `findByBankCode($countryCode, $bankIdentifier, null)` — the same
bank code, but with the branch segment dropped. That fallback is what lets an IBAN carrying a specific
branch still resolve against a bank-level-only row. This also applies retroactively to data seeded by
v1.1's `BancoDeEspanaImporter` (ES): before this fallback existed, an ES IBAN's specific 4-digit branch
had to be seeded separately for `resolve()` to find anything; now the bank-level row Banco de España
actually publishes is enough.

## The EPC SEPA Register importer

`EpcRegisterImporter` (`src/Import/Importers/EpcRegisterImporter.php`) is the one bundled importer that
isn't a single-country class — it's **parameterized by constructor** (`new EpcRegisterImporter('IE')`)
and `ImporterRegistry::registerDefaults()` registers it five times, once each for `GB`, `GI`, `IE`,
`LV` and `RO`: the SEPA countries whose IBAN `bank_code` is exactly the BIC's 4-letter institution
prefix and that had no dedicated national importer already covering that shape (BG/MT/NL share the same
bank-code-from-BIC-prefix pattern but already have a national importer, so they aren't also registered
under `epc`).

- **Source**: the European Payments Council's SEPA Register, one CSV export per scheme —
  `sct`, `sct_inst`, `sdd_core`, `sdd_b2b` — all at
  `https://www.europeanpaymentscouncil.eu/sites/default/files/participants_export/{scheme}/{scheme}.csv`.
  `sourceUrl()` points at the `sct` file (the scheme this importer's offline single-file mode consumes).
- **BIC-prefix mapping**: `bank_code = strtoupper(substr($bic, 0, 4))`. The register's `Country` column
  is the participant's country as a full, all-caps English name (e.g. `"IRELAND"`, `"UNITED KINGDOM"`),
  not an ISO code — the importer maps each registered country's ISO2 to that exact name internally.
  Rows with a BIC shorter than 4 characters are skipped; several BICs sharing the same 4-letter prefix
  are deduped to the first one seen.
- **SEPA reachability flags**: a **live** run (no `$localFile`) fetches all four scheme files and sets
  `sepa_sct`/`sepa_sct_inst`/`sepa_sdd_core`/`sepa_sdd_b2b` to `true`/`false` per bank code, based on
  whether that bank appears in each scheme's own export — or leaves a flag `null` if that particular
  scheme's file couldn't be fetched at all (so "unreachable" is never confused with "unknown"). An
  **offline** run (`--file=<path-to-one-scheme-csv>`) only ever sees the one file it's handed, so it
  sets `sepa_sct: true` and leaves the other three flags unset.
- **Attribution requirement**: the EPC's license terms permit viewing, downloading and reusing SEPA
  Register data when enriched into a broader dataset (this package's exact use), on condition of
  crediting the EPC and not reselling the data as-is — hence `license()` returning
  `'EPC SEPA Register (credit EPC, no resale as-is)'`.

## `.xlsx` sources and offline-only imports

Seven importers (BE, HR, LU, MT, HU, NO, GE) consume a genuine `.xlsx` (OOXML) spreadsheet, read
natively via `Import\Support\XlsxReader` (`ZipArchive` + `SimpleXMLElement`, requires `ext-zip`) — this
package deliberately does not depend on a full spreadsheet library such as PhpSpreadsheet.
`XlsxReader::readFirstSheet()` returns the first worksheet as a plain 0-indexed grid of cell strings;
each importer locates its own header row and column-by-name within that grid (see each class's
`locateHeader()`). v1.1's NL (`betaalvereniging`) importer predates `XlsxReader` and takes a different
path: its source is also an `.xlsx`, but it's designed to consume a CSV the operator exports from it by
hand, not the `.xlsx` bytes directly.

Several sources are landing pages or portals rather than a stable direct file URL, and a live
(no-`--file`) call to `rows()` may not reliably fetch real data — prefer `--file=<path>` (an offline
import from a file you download yourself) for these:

- **LU** (`abbl`) — the stable URL is a landing page whose actual download is a rotating, per-request
  token URL; a live fetch cannot resolve it at all.
- **NO** (`bits`) — the landing page happens to serve the `.xlsx` directly today, but this isn't
  guaranteed to stay stable.
- **GE** (`nbg`) — the landing page links a date-stamped file whose name changes on every republish.
- **UA** (`nbu`) — the NBU portal is known to reject requests without a browser-like User-Agent/session
  (403); offline `--file` is the tested/supported path.
- **KZ** (`nbk`) — the data.egov.kz dataset API requires a portal-issued API key this package doesn't
  ship; offline `--file` is the tested/supported path.

## Provenance: how imported data is stored

`ImportRunner::run()` upserts each yielded row into the `banks` table by the natural key
`(country_code, bank_code, branch_code)` (unique-indexed since v1.0's
`src/Database/Migrations/2026-07-10-000001_CreateBanksTable.php`), and stamps 4 provenance columns
onto every row written in a given run:

- `source_id` — the importer's `sourceId()` (e.g. `'oenb'`).
- `source_license` — the importer's `license()` string, verbatim.
- `source_version` — the run's date (`Y-m-d`, taken from a single timestamp computed once per
  `run()` call, not per row, so every row from the same run shares the same version marker).
- `updated_at` — the same run timestamp, full precision (`Y-m-d H:i:s`).

This means every `BankResult` returned by `resolve()` carries its own attribution back to the caller
(`BankResult::$sourceId`, `$sourceVersion`, `$sourceLicense`) — see
[`docs/usage.md`](usage.md#resolving-bank-data-nullprovider-vs-databaseprovider). A row missing the
required `bank_code` is skipped (not written), counted in `ImportReport::$skipped`, and explained in
`ImportReport::$messages`.

## Writing a custom importer

Implement `Daycry\Iban\Contracts\ImporterInterface` for your own `(country, source)` pair — it has no
CI4 dependency, so it can be developed and unit-tested standalone:

```php
use Daycry\Iban\Contracts\ImporterInterface;

final class MyBankListImporter implements ImporterInterface
{
    public function countryCode(): string { return 'XX'; }
    public function sourceId(): string { return 'my-source'; }
    public function sourceName(): string { return 'My Central Bank'; }
    public function license(): string { return 'CC-BY-4.0'; }
    public function sourceUrl(): string { return 'https://example.org/bank-list.csv'; }

    public function rows(?string $localFile = null): iterable
    {
        // Fetch $localFile if given, else sourceUrl() live, over plain PHP
        // (file_get_contents()/fgetcsv()/streams, or Import\Support\XlsxReader for .xlsx)
        // — never a framework HTTP client, so this stays usable outside CI4 too.
        yield ['bank_code' => '1234', 'name' => 'Example Bank'];
    }
}
```

Register it either by extending `ImporterRegistry`:

```php
use Daycry\Iban\Import\ImporterRegistry;

final class MyImporterRegistry extends ImporterRegistry
{
    protected function registerDefaults(): void
    {
        parent::registerDefaults(); // keep the 30 bundled importers
        $this->register(new MyBankListImporter());
    }
}
```

or, for a one-off script, by calling `register()` directly on a plain `ImporterRegistry` instance.
`UpdateCommand` always constructs `new ImporterRegistry()` itself, so wiring a custom registry into
`iban:update` today means subclassing `UpdateCommand` (or copying its `run()` body) in your own app —
there is no config hook to swap the registry class as of v1.2.

Run it through `ImportRunner` exactly like a bundled importer:

```php
use Daycry\Iban\Import\ImportRunner;
use Daycry\Iban\Models\BankModel;

$report = (new ImportRunner())->run(new MyBankListImporter(), new BankModel(), dryRun: false);
$report->fetched; $report->imported; $report->skipped; $report->messages;
```

Keep the same licensing discipline this package follows for its own 30 importers (see
[`docs/licensing.md`](licensing.md)): don't bundle the source's actual data file in your app's
repository if its license doesn't allow redistribution — ship the importer code, and let the operator
supply the data via a live fetch or `--file`.
