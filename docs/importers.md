# Bank-data importers

Reference for the v1.1 importer subsystem: the `ImporterInterface` contract, the `iban:update`
command, the 5 bundled official-source importers, how provenance is stored, and how to write a
custom importer. See [`docs/licensing.md`](licensing.md) first for *why* this package ships zero
bundled bank data and imports everything on-demand instead.

- [Overview](#overview)
- [`iban:update` usage](#ibanupdate-usage)
- [The 5 bundled importers](#the-5-bundled-importers)
- [Provenance: how imported data is stored](#provenance-how-imported-data-is-stored)
- [Writing a custom importer](#writing-a-custom-importer)

## Overview

Four pieces make up the importer subsystem:

| Piece | Location | Framework-free? | Role |
|---|---|---|---|
| `ImporterInterface` | `src/Contracts/ImporterInterface.php` | Yes | Per-`(country, source)` contract: describes the source and yields normalized rows. |
| `ImportReport` | `src/Import/ImportReport.php` | Yes | Immutable result of one import run: `fetched`/`imported`/`skipped`/`dryRun`/`messages`. |
| `ImporterRegistry` | `src/Import/ImporterRegistry.php` | Yes | In-memory catalog of `ImporterInterface` instances, keyed by `(countryCode, sourceId)`. `registerDefaults()` wires in the 5 bundled importers. |
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
from `sourceUrl()`.

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
Registered importers: 5
+---------+------------------+-------------------------------+------------------------------------+
| Country | Source           | Name                          | License                             |
+---------+------------------+-------------------------------+------------------------------------+
| AT      | oenb             | Oesterreichische Nationalbank | CC-BY-4.0 (OeNB)                    |
| DE      | bundesbank       | Deutsche Bundesbank           | Deutsche Bundesbank                 |
| CH      | six              | SIX Interbank Clearing        | SIX Interbank Clearing (free use)   |
| NL      | betaalvereniging | Betaalvereniging Nederland    | Betaalvereniging Nederland (see terms) |
| ES      | bde              | Banco de España               | Banco de España                     |
+---------+------------------+-------------------------------+------------------------------------+
Select one with --country=/--source= to run it (add --dry-run to preview).
```

Selecting a source (by `--country`, `--source`, or both) runs each match through `ImportRunner`
against `Config\Iban::$table`/`$dbGroup`, and prints its `ImportReport`:

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
```

(`fetched`/`imported`/`skipped` counts above are illustrative — `<N>`/`...` stand in for whatever the
live source or the file you point `--file` at actually contains at run time; `fetched === imported`
only when nothing was skipped.)

A selection matching no registered importer prints `No bundled importer matches that selection.` and
exits `0` (no exception).

## The 5 bundled importers

None of these ship any actual data in the repository — running them is always something the operator
does deliberately, against a live network fetch or a locally downloaded/exported file.

| Country | `--source=` | Format | License | Official URL |
|---|---|---|---|---|
| AT | `oenb` | `;`-delimited CSV (header-mapped columns), UTF-8/Windows-1252 fallback | CC-BY-4.0 | `https://www.oenb.at/docroot/downloads_observ/bankstellenverzeichnis.csv` |
| DE | `bundesbank` | Fixed-width ASCII, 168 chars/record, ISO-8859-1 | Free use, mandatory unaltered attribution ("Source: Deutsche Bundesbank") | `https://www.bundesbank.de/resource/blob/602632/.../blz-aktuell-txt-data.txt` (rotates quarterly — see class docblock for the fallback download page) |
| CH | `six` | `;`-delimited CSV (Bank Master V3, fixed column positions), UTF-8 | "Free to use" — BIC column remains SWIFT's property, not for standalone redistribution | `https://api.six-group.com/api/epcd/bankmaster/v3/bankmaster_V3.csv` |
| NL | `betaalvereniging` | `;`-delimited CSV **exported from** the source `.xlsx` (no spreadsheet-parsing dependency), UTF-8 | Reproduction/redistribution requires Betaalvereniging Nederland's written consent — import-only via `--file`, not bundled or auto-fetched in practice | `https://www.betaalvereniging.nl/wp-content/uploads/BIC-lijst-NL.xlsx` |
| ES | `bde` | `,`-delimited CSV, UTF-8 with leading BOM | Reproduction authorized subject to attribution + no alteration (see class docblock for the exact clause) | `https://www.bde.es/webbe/es/estadisticas/otras-clasificaciones/clasificacion-entidades/listas-instituciones-financieras/listas-instituciones-financieras-monetarias-pais/lista-mfi-es.csv` |

Each importer's class docblock (`src/Import/Importers/*.php`) documents its exact column
layout/positions, encoding quirks, and any row-filtering rules (e.g. OeNB and Bundesbank both dedupe
multiple physical-branch rows per bank code down to one head-office record, since the `banks` table's
natural key has no branch segment for these two countries). Read the relevant docblock before running
an importer against a freshly downloaded file — publishers change layouts without notice, and every
docblock says so explicitly ("CAVEAT: parsing targets the documented/observed format as of this
release").

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
        // (file_get_contents()/fgetcsv()/streams) — never a framework HTTP client,
        // so this stays usable outside CI4 too.
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
        parent::registerDefaults(); // keep the 5 bundled importers
        $this->register(new MyBankListImporter());
    }
}
```

or, for a one-off script, by calling `register()` directly on a plain `ImporterRegistry` instance.
`UpdateCommand` always constructs `new ImporterRegistry()` itself, so wiring a custom registry into
`iban:update` today means subclassing `UpdateCommand` (or copying its `run()` body) in your own app —
there is no config hook to swap the registry class as of v1.1.

Run it through `ImportRunner` exactly like a bundled importer:

```php
use Daycry\Iban\Import\ImportRunner;
use Daycry\Iban\Models\BankModel;

$report = (new ImportRunner())->run(new MyBankListImporter(), new BankModel(), dryRun: false);
$report->fetched; $report->imported; $report->skipped; $report->messages;
```

Keep the same licensing discipline this package follows for its own 5 importers (see
[`docs/licensing.md`](licensing.md)): don't bundle the source's actual data file in your app's
repository if its license doesn't allow redistribution — ship the importer code, and let the operator
supply the data via a live fetch or `--file`.
