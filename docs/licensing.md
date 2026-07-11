# Data Licensing

`daycry/iban` is MIT-licensed (see [`LICENSE`](../LICENSE)). This note explains why the **structural
country registry** (`src/Registry/data/countries.php`, 78 countries) can be MIT-licensed even though
IBAN structure data has a tangled licensing history, and why v1.0 ships **zero bundled bank-entity
data**. See [`docs/registry-authoring.md`](registry-authoring.md) for the full authoring methodology
this page summarizes the legal rationale for.

## The core distinction: facts vs. compilation

- **Individual facts are not copyrightable.** "Spain's IBAN is 24 characters long," "the bank
  identifier is 4 numeric digits starting at offset 4" — these are observations about a public
  standard (ISO 13616), not creative expression. No one owns them.
- **A compiled *collection* of facts, arranged and expressed a particular way, can be copyrighted** —
  or protected by database rights in some jurisdictions (notably the EU) — even when none of the
  individual facts inside it are protectable on their own.

This is why `daycry/iban` never copies bytes, rows, or files from an existing compilation, no matter
how factual its contents. Instead, the registry is **independently authored**: each country's structure
is derived from public specification documents, transcribed by hand, then cross-checked against
independent references purely to catch transcription errors (see
[`docs/registry-authoring.md`](registry-authoring.md#cross-check-mit-reference-libraries)) — never
copy-pasted.

## What is NOT bundled, and why

| Source | Why it's excluded |
|---|---|
| **SWIFT IBAN Registry** (the official PDF/TXT registry file published by SWIFT) | Licensed non-commercial, no-derivatives, plus database rights over the compilation. Bundling it (or data mechanically derived from it) in an MIT-licensed package would violate that license and expose downstream commercial users to risk. |
| **SWIFT BIC Directory / SwiftRef** | Proprietary, paid data product. Never bundled at any point. |
| **`globalcitizen/php-iban`'s `registry.txt`** | LGPL-3.0 — a copyleft license. Copying its compiled registry table (even if individual facts inside it are themselves uncopyrightable) would pull LGPL obligations into this MIT package. Used only as an *architecture* reference (e.g. "avoid its ambiguous empty-string vs. absent-field modeling" — see `ParsedIban`'s use of `null` for "field doesn't exist" vs. `BankResult`'s `null` for "unresolved lookup"), never as a data source. |
| **Wikipedia's IBAN formats table** | CC BY-SA — copyleft, requires attribution *and* share-alike licensing of derivative works. Incompatible with a plain MIT release without carrying that obligation forward. |
| **EPC Register of SEPA Participants (per-PSP reachability data)** | Large, volatile, and not needed for v1.0's scope. Only the much smaller, stable **country-level** SEPA scope list (EPC409-09 — "is country X in SEPA at all") is hardcoded into the registry, as the `sepa` field on `CountryStructure` / `ParsedIban::$sepaCountry`. Per-PSP reachability (`BankResult::$sepaSct`, `$sepaSctInst`, `$sepaSddCore`, `$sepaSddB2b`) is left `null` unless a configured `ProviderInterface` (e.g. a future licensed importer) supplies it. |

Because of all of the above, **v1.0 ships an empty `banks` table** (`Daycry\Iban\Database\Seeds\BanksSeeder` intentionally inserts nothing) and `iban:update` is a **documented no-op** that prints these
exact licensing constraints rather than silently doing nothing — see
[`docs/usage.md`](usage.md#spark-commands). Bank-entity resolution (`resolve()`) still works end to end
via `DatabaseProvider`; it simply has no data to find until you seed it yourself or a future licensed
importer does.

## Independent authorship + MIT cross-check

The authoring workflow for each `countries.php` entry (detailed in
[`docs/registry-authoring.md`](registry-authoring.md)):

1. Consult independent, authoritative sources: the ISO 13616 specification, ECB/EPC publications,
   central bank documentation.
2. Record the facts by hand: IBAN length, BBAN token grammar, field offsets.
3. **Cross-check** the recorded facts against two independent, **MIT-licensed** reference
   implementations — [`cmpayments/iban`](https://github.com/cmpayments/iban) and
   [`ixnode/php-iban`](https://github.com/ixnode/php-iban) — purely to catch transcription mistakes, not
   to copy their data verbatim.
4. Verify algorithmically: every registry entry's `example` IBAN must satisfy the MOD-97 (ISO 7064)
   checksum, parameterized over all 78 countries in the test suite (`tests/Core/CountryValidationTest.php`
   and friends).

This produces a registry that is **independently authored from public facts**, license-compatible with
the reference implementations used to cross-check it (both MIT), and therefore itself freely
MIT-licensable — with no SWIFT, LGPL, or CC BY-SA obligations attached.

## `Registry::VERSION`

`Daycry\Iban\Registry\Registry` declares a version marker as legal documentation, not just a build
number:

```php
public const string VERSION = '2026-07 (78 countries; independently authored, not derived from the SWIFT IBAN Registry file)';
```

This exists to make the independent-authorship claim **traceable and machine-visible** at runtime — an
auditor or downstream commercial user can inspect `Registry::VERSION` directly instead of relying on
documentation alone. When the registry is regenerated (see the annual-refresh process in
[`docs/registry-authoring.md`](registry-authoring.md#annual-refresh-and-registry-generator)), `VERSION`
is updated to the new date, but the "not derived from the SWIFT IBAN Registry" phrasing is preserved —
it is the operative legal statement, not incidental wording.

## v1.1 source roadmap (bank-entity data)

None of the above blocks bank-entity *structural* resolution (`DatabaseProvider` + your own seeded
data) today. As of v1.1 the package bundles per-source importers for five official registries — but still ships
**no bank data in the repo**: the operator runs `iban:update --source=... [--file=...]` to import
into their own `banks` table (see [`docs/importers.md`](importers.md)). The five sources, in order of
license cleanliness:

1. **Austria (OeNB)** — CC BY 4.0. Attribution-only, no share-alike — the cleanest option.
2. **Switzerland (SIX)** — published as "free to use" (the BIC column remains SWIFT's property).
3. **Germany (Bundesbank)** — free to use with mandatory, unaltered attribution ("Deutsche
   Bundesbank").
4. **Netherlands (Betaalvereniging)** — the source is an Excel export (consumed as CSV; no Excel
   dependency); its terms require consent to redistribute, so it is import-path-only, never bundled.
5. **Spain (BdE)** — no standard open license; imported from the machine-readable MFI list under
   Banco de España's conditional attribution terms (the literal Registro de Entidades is portal/PDF
   only). Confirm terms before commercial redistribution.

Each source is imported via a per-source `ImporterInterface` (`countryCode()`, `sourceId()`,
`sourceName()`, `license()`, `sourceUrl()`, `rows(?string $localFile = null)`), with `source_id` /
`source_version` / `source_license` recorded per row in the `banks` table (present since the v1.0
schema — see `src/Database/Migrations/2026-07-10-000001_CreateBanksTable.php`) so every resolved
`BankResult` carries its own attribution back to the caller (`BankResult::$sourceId`,
`$sourceVersion`, `$sourceLicense`).
