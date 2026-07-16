# Bank-data importers

Reference for the importer subsystem: the `ImporterInterface` contract, the `iban:update` command, the
44 bundled official-source importers, the bank-level resolution model, the EPC SEPA Register importer,
how provenance is stored, and how to write a custom importer. See [`docs/licensing.md`](licensing.md)
first for *why* this package ships zero bundled bank data (with one narrow, factual curated exception)
and imports everything on-demand instead.

- [Overview](#overview)
- [`iban:update` usage](#ibanupdate-usage)
- [The 44 bundled importers](#the-44-bundled-importers)
- [Source shapes: HTML, offline `--file`, and curated data](#source-shapes-html-offline---file-and-curated-data)
- [Obtaining the offline `--file` sources](#obtaining-the-offline---file-sources)
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
| `ImporterRegistry` | `src/Import/ImporterRegistry.php` | Yes | In-memory catalog of `ImporterInterface` instances, keyed by `(countryCode, sourceId)`. `registerDefaults()` wires in all 44 bundled importers. |
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
iban:update [--all] [--country=<cc>] [--source=<id>] [--dry-run] [--file=<path>]
```

| Flag | Meaning |
|---|---|
| `--all` | v1.2: run **every** bundled importer (all 44) in one invocation instead of just one selected source — see the full `--all` reference (failure isolation, aggregate summary, the `--country`/`--file` interactions) in [`docs/usage.md`](usage.md#spark-commands)'s `iban:update` entry. |
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
Registered importers: 44
+---------+------------------+-------------------------------+------------------------------------+
| Country | Source           | Name                          | License                             |
+---------+------------------+-------------------------------+------------------------------------+
| AT      | oenb             | Oesterreichische Nationalbank | CC-BY-4.0 (OeNB)                    |
| DE      | bundesbank       | Deutsche Bundesbank           | Deutsche Bundesbank                 |
| CH      | six              | SIX Interbank Clearing        | SIX Interbank Clearing (free use)   |
| NL      | betaalvereniging | Betaalvereniging Nederland    | Betaalvereniging Nederland (see terms) |
| ES      | bde              | Banco de España               | Banco de España                     |
| CZ      | cnb              | Czech National Bank           | Czech National Bank (cite source, no changes) |
| ...     | ...              | ... (36 more)                  | ...                                  |
| GB/GI/IE/LV/RO | epc       | European Payments Council (SEPA Register) | EPC SEPA Register (credit EPC, no resale as-is) |
+---------+------------------+-------------------------------+------------------------------------+
Select one with --country=/--source= to run it (add --dry-run to preview).
```

(The full 44-row listing is in [the table below](#the-44-bundled-importers) — the block above is
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

## The 44 bundled importers

Most of these ship **no** actual data in the repository — running them is always something the operator
does deliberately, against a live network fetch or a locally downloaded/exported file. The three
**curated** micro-jurisdiction importers (AD, VA, SM) are the one narrow exception: they yield a small,
independently-authored *factual* map bundled as `src/Import/Importers/data/<cc>.php` (see
[`docs/licensing.md`](licensing.md#curated-micro-jurisdiction-bank-data-the-narrow-exception) and
[Source shapes](#source-shapes-html-offline---file-and-curated-data) below). `--source=` is shared by
`LI` and `CH` (both `six`, the same SIX Bank Master V3 file, filtered by country), by the five EPC
registrations (all `epc`, disambiguated by `--country=`), and by `FR` and `MC` (both `regafi`, the same
REGAFI dataset — Monaco's entities carry a French CIB, so one importer resolves both).

| Country | `--source=` | Publisher | Format | License |
|---|---|---|---|---|
| AT | `oenb` | Oesterreichische Nationalbank | `;`-CSV, UTF-8/Windows-1252 fallback | CC-BY-4.0 (OeNB) |
| DE | `bundesbank` | Deutsche Bundesbank | Fixed-width ASCII (168 chars/record), ISO-8859-1 | Deutsche Bundesbank |
| CH | `six` | SIX Interbank Clearing | `;`-CSV (Bank Master V3), UTF-8 | SIX Interbank Clearing (free use) |
| NL | `betaalvereniging` | Betaalvereniging Nederland | `;`-CSV export of the source `.xlsx`, UTF-8 | Betaalvereniging Nederland (see terms) |
| ES | `bde` | Banco de España | `,`-CSV, UTF-8 with leading BOM | Banco de España |
| CZ | `cnb` | Czech National Bank | `;`-CSV, UTF-8 with leading BOM | Czech National Bank (cite source, no changes) |
| GR | `hba` | Hellenic Bank Association (HEBIC) | `;`-CSV, Windows-1253 | Hellenic Bank Association (HEBIC) |
| SI | `bsi` | Bank of Slovenia | `;`-CSV, Windows-1250 | Bank of Slovenia (cite source, no changes) |
| SK | `nbs` | National Bank of Slovakia | `;`-CSV, Windows-1250 | National Bank of Slovakia |
| BG | `bnb` | Bulgarian National Bank | SpreadsheetML XML (`.xls` in name only), UTF-8 with BOM | Bulgarian National Bank |
| MD | `bnm` | National Bank of Moldova | XML (`latin1`-declared, decoded natively by libxml) | National Bank of Moldova |
| PL | `nbp` | Narodowy Bank Polski (EWIB) | XML, UTF-8 | Narodowy Bank Polski (public sector information, free reuse) |
| AZ | `cbar` | Central Bank of Azerbaijan | XML, UTF-8 | Central Bank of Azerbaijan |
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
| SE | `bankinfrastruktur` | Bankinfrastruktur BankData (community mirror of the official BSAB list) | `\|`-PSV, UTF-8 | MIT (Bankinfrastruktur BankData) |
| FR | `regafi` | REGAFI (ACPR / Banque de France) | JSON (Opendatasoft export), UTF-8 — `cib` is a serialized JSON array, expanded one row per 5-digit code; name only, no BIC | Licence Ouverte / Etalab (attribution) |
| MC | `regafi` | REGAFI (ACPR / Banque de France) | JSON — same dataset as FR, filtered to Monaco (its entities carry a French CIB) | Licence Ouverte / Etalab (attribution) |
| EE | `pangaliit` | Eesti Pangaliit (Estonian Banking Association) | HTML table (via `HtmlTableReader`); 2-digit code, handles doubles (Luminor 96/17) and leading zero (TBB 00) | Eesti Pangaliit (factual list) |
| ME | `cbcg` | Central Bank of Montenegro | HTML table (via `HtmlTableReader`); 3-digit code, public-entity range 714-931 filtered out | Central Bank of Montenegro |
| CY | `cbc` | Central Bank of Cyprus | Landing scrape (regex the date-stamped href) + `.xlsx` (via `XlsxReader`), browser User-Agent (WAF 403s otherwise) | Central Bank of Cyprus (Terms of Use, attribution) |
| AD | `andorran-banking` | Andorran Banking (curated) | Curated `data/ad.php` (4 codes / 3 banks; BIC cross-checked) | curated (factual, non-copyrightable) |
| PT | `bportugal` | Banco de Portugal (SICOI) | Offline `--file` text pre-extracted from the SICOI PDF (`pdftotext -layout`); 4-digit code, mojibake-repaired accents | Banco de Portugal (attribution) |
| MK | `nbrm` | NBRM (National Bank of North Macedonia) | Offline `--file` CSV exported from the legacy `.xls`/`.docx` (Cloudflare-gated); 3-digit code, Windows-1251 Cyrillic fallback | NBRM (regulatory roster) |
| VA | `vatican` | Vatican City / IOR (curated) | Curated `data/va.php` (single entry: 001 = IOR / IOPRVAVX) | curated (factual, non-copyrightable) |
| SM | `bcsm` | Banca Centrale di San Marino (curated) | Curated `data/sm.php` (4 banks by 5-digit ABI) | curated (factual, non-copyrightable) |
| IT | `agenzia-entrate` | Agenzia delle Entrate (F24) | HTML table (via `HtmlTableReader`); `Codice ABI` zero-padded to 5 digits, name only (no BIC), partial (F24-adhering banks) | Agenzia delle Entrate (F24, partial list) |
| RS | `nbs-rs` | National Bank of Serbia (NBS) | Offline `--file` operator-prepared `code;name;bic` CSV (join of two misaligned NBS PDFs); 3-digit code | National Bank of Serbia (regulatory directory) |
| FI | `finanssiala` | Finance Finland (Finanssiala ry) | Offline `--file` CSV pre-extracted from the PDF; variable-length `Rahalaitostunnus` expanded to the fixed 3-digit bank_code (ranges/lists), 4-digit post-2024 codes dropped | Finanssiala ry (no reuse terms; fetch-only) |

The License column above is the exact string each importer's `license()` returns (and thus what gets
stored verbatim in `banks.source_license` — see
[Provenance](#provenance-how-imported-data-is-stored)). A few publishers state further terms beyond
that short string, which aren't part of the stored value: Deutsche Bundesbank permits free use with
mandatory attribution; Betaalvereniging Nederland's "(see terms)" means written consent is needed to
redistribute; Banco de España requires attribution and no alteration; Hellenic Bank Association (HEBIC)
and Central Bank of Azerbaijan state no explicit license. Among the SEPA-coverage batch: REGAFI (FR/MC)
is Licence Ouverte / Etalab (mandatory attribution + date); the SE mirror is MIT while the *official*
BSAB list it mirrors is not open (PDF/DOCX); EE/ME/IT state no explicit reuse license (consumed as
fetch-on-demand factual lists); Banco de Portugal permits reuse with attribution; **Finanssiala ry
(FI) states no reuse terms at all**, so its importer is strictly fetch-only from an operator-supplied
`--file` and its data is never bundled; the three curated sources (AD/VA/SM) carry
`curated (factual, non-copyrightable)` per
[the curated-data exception](licensing.md#curated-micro-jurisdiction-bank-data-the-narrow-exception).

Each importer's class docblock (`src/Import/Importers/*.php`) documents its exact column
layout/positions, encoding quirks, and any row-filtering/dedup rules (e.g. OeNB and Bundesbank dedupe
per-branch rows down to one head-office record per bank code; several XML/JSON sources dedupe several
BICs/settlement codes sharing the same institution prefix). Read the relevant docblock before running
an importer against a freshly downloaded file — publishers change layouts without notice, and every
docblock says so explicitly ("CAVEAT: parsing targets the documented/observed format as of this
release").

## Source shapes: HTML, offline `--file`, and curated data

The SEPA-coverage batch added three capabilities beyond the CSV/fixed-width/XML/`.xlsx`/JSON shapes the
earlier importers use:

- **HTML tables — `Import\Support\HtmlTableReader`.** A small framework-free reader
  (`DOMDocument`/`libxml`, requires `ext-dom` — the HTML analogue of `XlsxReader`): `readTables()`
  returns every `<table>` as a 0-indexed grid of cell strings, and the static `locateHeader()` finds a
  header row and its columns by name. Used by **EE** (`pangaliit`), **ME** (`cbcg`), **IT**
  (`agenzia-entrate`), and by **CY** (`cbc`, to scrape the date-stamped `.xlsx` href off the landing
  page). HTML scraping is structurally fragile — every consumer's docblock documents the exact
  header/column assumptions and warns to re-verify after a site redesign.
- **Offline `--file` text/PDF and operator-prepared CSV.** This package still ships **no PDF reader**;
  where the authoritative source is a PDF (or a Cloudflare/bot-blocked download), the importer is
  `--file`-only and consumes text/CSV the operator pre-extracts. The documented recipe is
  `pdftotext -layout -enc UTF-8 <pdf> out.txt`. **PT** (`bportugal`) parses the extracted SICOI text
  directly; **RS** (`nbs-rs`) and **FI** (`finanssiala`) consume a small operator-prepared
  semicolon-CSV (RS joins two misaligned NBS PDFs *by code*; FI expands a variable-length
  `Rahalaitostunnus` to the fixed 3-digit `bank_code`); **MK** (`nbrm`) consumes a CSV exported from the
  legacy `.xls`/`.docx`. A live, no-`--file` call on any of these fetches the raw source bytes, finds no
  parseable data, and gracefully yields nothing rather than erroring.
- **Curated micro-jurisdiction data.** For SEPA micro-jurisdictions with *no* machine-readable directory
  at all, a curated importer yields a constant, independently-authored factual map from
  `src/Import/Importers/data/<cc>.php`, independent of `--file`/network: **AD** (`andorran-banking`, 4
  codes / 3 banks), **VA** (`vatican`, the single IOR entry), **SM** (`bcsm`, 4 banks by ABI). This is
  the one deliberate exception to "ship no bank data"; see
  [`docs/licensing.md`](licensing.md#curated-micro-jurisdiction-bank-data-the-narrow-exception).

## Obtaining the offline `--file` sources

The **live-fetch** importers (SE, FR, MC, CY, EE, ME, IT) and the **curated** ones (AD, VA, SM) need no
file — just `php spark iban:update --country=<cc>`. The four **PDF-/Cloudflare-gated** importers (PT, FI,
RS, MK) are `--file`-only: download the official source, prepare a text/CSV as documented below, and
pass it with `--file`. The PDF ones need [`pdftotext`](https://poppler.freedesktop.org/) (the
`poppler-utils` package). Add `--dry-run` to preview any import without writing to the `banks` table.
Each importer's class docblock carries the same recipe plus its exact parsing rules — re-verify against
a freshly downloaded file before production, since publishers change layouts and URLs without notice.

### PT — Banco de Portugal (SICOI)

1. Download the *"BIC associated with IBANs of PSPs participating in SICOI"* PDF from
   <https://www.bportugal.pt/en/page/sicoi> (the file URL is date-stamped and rotates; the landing page
   bot-blocks automated fetches, which is why this is `--file`-only).
2. Extract its text preserving the fixed-width column alignment, then import:

```bash
pdftotext -layout -enc UTF-8 "bic_linked_with_ibans_<date>.pdf" bportugal.txt
php spark iban:update --source=bportugal --country=PT --file=bportugal.txt
```

The parser reads each data line as `4-digit code | PSP name | BIC`; accented names extracted without
`-enc UTF-8` (Windows-1252 mojibake) are repaired automatically.

### FI — Finance Finland (Finanssiala ry)

1. Download the *"Suomalaiset rahalaitostunnukset ja BIC-koodit"* PDF from
   <https://www.finanssiala.fi/julkaisut/suomalaiset-rahalaitostunnukset-ja-bic-koodit/>.
2. Extract it and tidy the three columns into a **semicolon**-delimited UTF-8 CSV with the header
   `Rahalaitos;Rahalaitostunnus;BIC` (semicolon, because a code cell can hold a comma list like
   `405, 497`), then import:

```bash
pdftotext -layout -enc UTF-8 "suomalaiset-rahalaitostunnukset-ja-bic-<date>.pdf" fi.txt
# arrange the columns into  Rahalaitos;Rahalaitostunnus;BIC  → fi.csv
php spark iban:update --source=finanssiala --country=FI --file=fi.csv
```

The importer expands the variable-length `Rahalaitostunnus` (single values, `1 ja 2` lists, `470-479`
ranges) into the fixed 3-digit `bank_code`. Post-2024 four-digit codes (leading 72–78) cannot be keyed
on 3 digits and are reported as skipped in the `ImportReport`.

### RS — National Bank of Serbia (NBS)

1. Download both NBS PDFs from <https://www.nbs.rs/>: `pregled_racuna_banka.pdf` (code → BIC) and
   `pu_jedinstveni_id_brojevi.pdf` (code → name).
2. Extract both and build a **semicolon**-delimited UTF-8 CSV with the header `code;name;bic`, joining
   the two files **by code** (not by row order — the two-column PDF layout is misaligned), then import:

```bash
pdftotext -layout -enc UTF-8 pu_jedinstveni_id_brojevi.pdf names.txt
pdftotext -layout -enc UTF-8 pregled_racuna_banka.pdf bics.txt
# cross-join by 3-digit code → rs.csv  (code;name;bic)
php spark iban:update --source=nbs-rs --country=RS --file=rs.csv
```

### MK — NBRM (National Bank of North Macedonia)

1. `nbrm.mk` sits behind a Cloudflare challenge, so download it **from a browser**: the
   *"Листа на доделени водечки броеви на банките"* roster (`.xls`/`.docx`).
2. Open it in Excel/LibreOffice and *Save As* a **comma**-delimited, UTF-8 CSV, keeping the header row
   (`Р.бр,SWIFT BIC,Назив на банка,Водечки број`), then import:

```bash
php spark iban:update --source=nbrm --country=MK --file=mk.csv
```

> ⚠️ The only publicly confirmable roster is from 2014 — verify it and drop any defunct institutions
> (e.g. Eurostandard banka, leading number 370, liquidated 2020) before importing.

## Coverage matrix

44 of the 78 registry countries now have a bundled importer. Of the 42 SEPA-scheme countries, **38 now
resolve** through a bundled importer — up from 24 before the SEPA-coverage batch, which added 14
(SE, FR, MC, EE, ME, CY, AD, PT, MK, VA, SM, IT, RS, FI). The four SEPA countries still unresolved are
**AL, IS, LT** (documented, deferred — see below) and **DK** (tier D — no open source).

**Covered (44)** — grouped by source shape:

| Group | Countries |
|---|---|
| v1.1, CSV/fixed-width | AT, DE, CH, NL, ES |
| v1.2, CSV | CZ, GR, SI, SK, BR |
| v1.2, XML | BG, MD, PL, AZ |
| v1.2, `.xlsx` (`XlsxReader`) | BE, HR, LU, MT, HU, NO, GE |
| v1.2, JSON | IL, UA, KZ |
| v1.2, shared SIX source | LI (alongside CH) |
| v1.2, EPC SEPA Register | GB, GI, IE, LV, RO |
| SEPA batch, PSV | SE |
| SEPA batch, JSON (REGAFI, one importer) | FR, MC |
| SEPA batch, HTML (`HtmlTableReader`) | EE, ME, IT (+ CY, which scrapes the landing then reads `.xlsx`) |
| SEPA batch, offline `--file` (PDF/CSV) | PT, MK, RS, FI |
| SEPA batch, curated (`data/<cc>.php`) | AD, VA, SM |

The SEPA-coverage batch **removed FI from "deferred"**: v1.2 had parked it (the machine-readable file
was stale and its `rahalaitostunnus` is variable-length), but the batch ships `FinanceFinlandImporter`,
an offline `--file` importer with a bespoke range-expansion mapper that turns the variable-length source
code into the fixed 3-digit `bank_code` (4-digit post-2024 codes are a documented, reported loss). FI
keeps its v1.1 `FinnishNationalCheckValidator` too.

**Documented, deferred (source exists but not shipped)** — three SEPA countries where a source was found
but deliberately left unbuilt this pass (reasoning from the initiative's `research.md`):

| Country | `bank_code` | Why not shipped |
|---|---|---|
| IS | 4 digits | The 4-digit code is bank + branch, and there is no open, full Reiknistofa bankanna (RB) directory to author a complete map from; it doesn't fit exact-code resolution. A bank-level curated prefix map was scoped (D4) but deferred rather than authored without an authoritative branch list. |
| AL | 3 digits | The KIB → bank mapping lives only in the Bank of Albania IBAN-regulation PDF (Annex 4), and `bankofalbania.org` bot-blocks automated fetches; curating ~13 banks cross-checked against EPC/SWIFT was scoped but deferred. |
| LT | 5 digits | Lietuvos bankas publishes a complete financial-institution PDF (221 rows, code → BIC → name), but its **licence is unconfirmed** — the file carries an "LB INTERNAL" / "LB VIDAUS (ECB INTERNAL)" watermark despite being downloadable — so the enrichment is not shipped until the terms are confirmed. |

**Not viable — DK (+FO/GL), tier D.** Denmark has **no open, machine-readable, reusable** source for
its 4-digit `registreringsnummer` → bank mapping: the authoritative live registry
(`registreringsnumre.dk`, Mastercard Payment Services Denmark) is a paid subscription whose licence
**forbids reproduction and commercial use**, and the only free artefact (a Finanstilsynet PDF) is stale
(~2011). The Faroe Islands (**FO**) and Greenland (**GL**) share Denmark's `registreringsnummer` system,
so they inherit the same blocker (and are non-SEPA besides). DK/FO/GL are documented here and **not
built**; the only theoretical path is an operator-supplied `--file` under their own licence risk.

**Not viable — other non-SEPA buckets** (no clean, open, machine-readable, IBAN-bank-code-mapping source;
full per-country reasoning is in the v1.2 research record):

| Bucket | Countries |
|---|---|
| Paywalled / subscription only | (SEPA FR and IT are now covered via REGAFI / the Agenzia delle Entrate F24 list; the canonical paid registries — France's FIB, Italy's SIA-Nexi ABI/CAB — remain tier D) |
| PDF-only, non-SEPA | EG, LB, MU, PK, QA, SC, TN, LC |
| Portal/HTML-only, non-SEPA | BA, SA, SV, TL, KW, JO, BH |
| SWIFT-only (`bank_code` = BIC prefix, non-SEPA so not in the EPC register) | DO, VG, IQ, ST, LY, MR, AE, CR, GT, XK |
| Other | PS (PMA's directory is address-only, currently unreachable, and PS's `bank_code` is itself SWIFT-derived), TR (the only downloadable file is a stale one-off 2022 export with no maintained refresh path or stated license), BY (a viable-looking NBRB `bic-rb.xlsx` exists but raises a sanctions/compliance concern, blocked format verification, unstated licence, BIC-prefix-derived code) |

IE, LV, RO, GI and GB were previously bucketed as SWIFT-only/portal-only/paywalled in the pre-EPC
research pass — they moved to "covered" once the EPC SEPA Register importer shipped, since the SEPA
Register publishes exactly the BIC each of their IBANs' `bank_code` is derived from. Likewise, most of
the SEPA-batch countries above were previously in the PDF-only / portal-only buckets and moved to
"covered" once `HtmlTableReader`, the offline `--file` importers, and the curated-data exception landed.

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

Eight importers (BE, HR, LU, MT, HU, NO, GE, and CY) consume a genuine `.xlsx` (OOXML) spreadsheet, read
natively via `Import\Support\XlsxReader` (`ZipArchive` + `SimpleXMLElement`, requires `ext-zip`) — this
package deliberately does not depend on a full spreadsheet library such as PhpSpreadsheet. CY differs in
that it first scrapes the Central Bank of Cyprus IBAN landing page for the date-stamped `.xlsx` href
(with a browser User-Agent, since the WAF 403s otherwise), then reads that file with `XlsxReader`.
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
- **CY** (`cbc`) — the download href is date-stamped and rotates; the WAF requires a browser
  User-Agent. A live fetch scrapes the stable landing page for the current href, but `--file` is the
  reliable fallback.
- **PT** (`bportugal`), **MK** (`nbrm`), **RS** (`nbs-rs`), **FI** (`finanssiala`) — PDF- or
  Cloudflare-gated sources with no PDF reader in the package; **offline `--file`** (operator-extracted
  text/CSV) is the only supported path. See
  [Source shapes](#source-shapes-html-offline---file-and-curated-data).

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
        parent::registerDefaults(); // keep the 44 bundled importers
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

Keep the same licensing discipline this package follows for its own 44 importers (see
[`docs/licensing.md`](licensing.md)): don't bundle the source's actual data file in your app's
repository if its license doesn't allow redistribution — ship the importer code, and let the operator
supply the data via a live fetch or `--file`.
