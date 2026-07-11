# Roadmap

`daycry/iban` v1.0 is feature-complete (see [`CHANGELOG.md`](../CHANGELOG.md)). This page tracks what's
planned beyond it, per §12 of the design spec
(`docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md`). Nothing here is scheduled or committed
— it's a statement of direction, not a promise.

For the historical, per-task breakdown of how v1.0 itself was built, see
[`docs/roadmap/2026-07-10-daycry-iban-v1/`](roadmap/2026-07-10-daycry-iban-v1/) (`spec.md`,
`evaluation.md`, `improvement-plan.md`, `tasks.md`).

## v1.1 — real bank-data import

The single biggest gap in v1.0 is that `resolve()` has nothing to resolve out of the box: the `banks`
table ships empty and `iban:update` is a documented no-op (see
[`docs/licensing.md`](licensing.md) for why). v1.1's focus is closing that gap without compromising the
licensing discipline v1.0 established:

- **`ImporterInterface`** — a per-source contract:
  `countryCode(): string`, `sourceId(): string`, `license(): string`, `fetch(): iterable`,
  `import(): ImportReport`. Registered by `countryCode + sourceId`, so multiple sources can coexist per
  country (e.g. an official central-bank list plus a community-maintained supplement).
- **`iban:update` becomes a real importer**, not a scaffold: it downloads/ingests from registered
  `ImporterInterface`s, honoring the `--source=`/`--country=`/`--dry-run` flags already accepted (and
  currently ignored) by the v1.0 command.
- **Source priority**, by license cleanliness (see [`docs/licensing.md`](licensing.md#v11-source-roadmap-bank-entity-data)
  for the full rationale):
  1. Austria (OeNB) — CC BY 4.0.
  2. Switzerland (SIX) — "free to use."
  3. Germany (Bundesbank) — free to use, mandatory unaltered attribution.
  4. Netherlands (Betaalvereniging) — Excel export, marked incomplete pending review.
  5. Spain (BdE) — no clear open license; manual export, explicit review required.
- **Incremental update** — re-running an importer updates only changed rows (keyed by
  `country_code + bank_code + branch_code`, already unique-indexed in the v1.0 schema) instead of a
  full table rebuild.
- **Caching** — avoid re-fetching unchanged upstream sources on every `iban:update` run.
- **Additional national check-digit validators** — the `checkNational` hook (`Validator`'s
  `$nationalValidators` map, keyed by country code) already supports registering more than one
  validator; v1.0 ships only `ES`. Planned next: **BE**, **FR**, **IT** (exact algorithms TBD per
  country during implementation).

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
