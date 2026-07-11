# Roadmap

`daycry/iban` v1.1 is feature-complete (see [`CHANGELOG.md`](../CHANGELOG.md)). This page tracks what's
planned beyond it, per §12 of the design spec
(`docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md`). Nothing here is scheduled or committed
— it's a statement of direction, not a promise.

For the historical, per-task breakdown of how v1.0 itself was built, see
[`docs/roadmap/2026-07-10-daycry-iban-v1/`](roadmap/2026-07-10-daycry-iban-v1/) (`spec.md`,
`evaluation.md`, `improvement-plan.md`, `tasks.md`).

## v1.1 — shipped

Everything planned for v1.1 in the previous revision of this page has shipped — see
[`CHANGELOG.md`](../CHANGELOG.md#110---2026-07-11) for the authoritative list, and
[`docs/importers.md`](importers.md) for the full importer reference. Summary, with the actual API as
built (it differs from what was originally sketched here):

- **`ImporterInterface`** shipped with a different, real method set than originally planned:
  `countryCode(): string`, `sourceId(): string`, `sourceName(): string`, `license(): string`,
  `sourceUrl(): string`, `rows(?string $localFile = null): iterable` — no separate `fetch()`/
  `import()` pair; a single `rows()` generator covers both the live-fetch and offline-file cases, and
  the actual upsert-into-`banks` logic lives in `Import\ImportRunner`, not the importer itself.
  Registered by `(countryCode, sourceId)` via `Import\ImporterRegistry`, so multiple sources can
  coexist per country, as originally intended.
- **`iban:update` is a real importer command**, not a scaffold: `--country=`/`--source=`/`--dry-run`
  (all already accepted, previously ignored, in v1.0) now do something, plus a new `--file=<path>` for
  offline import from a previously-downloaded/exported file.
- **All 5 originally-prioritized sources shipped**, in the same license-cleanliness order this page
  previously described: Austria (OeNB, CC-BY-4.0), Switzerland (SIX, free use), Germany (Bundesbank,
  free use + mandatory attribution), Netherlands (Betaalvereniging, consumed as a CSV export of the
  source `.xlsx`, import-only via `--file` due to its redistribution terms), Spain (BdE, MFI-list CSV,
  attribution + no-alteration terms).
- **Additional national check-digit validators**: `BE`, `PT`, `SI`, `FI`, `FR` (+`MC`), `IT` (+`SM`)
  joined `ES` in `Core\Validator`'s default `$nationalValidators` map — more countries than the
  original BE/FR/IT shortlist, and `MC`/`SM` ride along for free since they share France's/Italy's BBAN
  structure and algorithm exactly. Estonia (`EE`) was evaluated and deliberately **not** added — see
  [`docs/usage.md`](usage.md#national-check-digit-validators) for why a generic implementation would
  be actively wrong for it.
- **Optional resolver cache** (`Providers\CachedProvider`, opt-in via `Config\Iban::$cacheTtl`) —
  addresses the "avoid re-querying on every lookup" goal from this page's original caching bullet, at
  the `resolve()` layer rather than the importer-fetch layer.
- **`Config\Iban::$defaultFormat`/`$checkNationalByDefault` are now actually honored** by the helper
  and commands (they were declared but inert in v1.0) — not part of the original v1.1 bullet list, but
  shipped alongside it as a related config-completeness fix.

**Not carried forward from the original plan**: incremental/changed-rows-only re-import wasn't built
as a distinct feature — `ImportRunner` always upserts every yielded row by natural key on each run
(cheap enough in practice for these source sizes); a future "only touch rows whose upstream data
actually changed" optimization remains open if a source's row count grows large enough to matter.

## v2.0 — external providers and standalone distribution

- **External provider plugins** — optional `ProviderInterface` implementations backed by third-party
  IBAN/BIC lookup APIs (e.g. an `IbanComProvider`-style integration), for consumers who prefer a live
  API over a self-hosted `banks` table. Opt-in only, never a default — `NullProvider` stays the
  zero-config default.
- **REST API** — an optional HTTP surface over the same `validate`/`parse`/`resolve` operations, for
  non-PHP consumers.
- **Standalone CLI** — a distributable CLI independent of the CodeIgniter 4 spark runner, for use in
  contexts where a full CI4 app isn't otherwise needed (CI pipelines, ad-hoc scripts).

## Out of scope indefinitely

- Bundling the SWIFT IBAN Registry, SwiftRef BIC Directory, globalcitizen's `registry.txt`, or
  Wikipedia's IBAN table, in any form — see [`docs/licensing.md`](licensing.md).
- Bundling the full EPC Register of SEPA Participants (per-PSP reachability); only the small,
  stable country-level SEPA scope list (EPC409-09) is in scope, and it already ships in v1.0
  (`ParsedIban::$sepaCountry`).
