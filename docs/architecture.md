# Architecture

> Concise architecture overview. Full usage documentation lives in [`docs/usage.md`](usage.md), and
> the importer subsystem in [`docs/importers.md`](importers.md). The package is feature-complete; the
> latest release adds BIC (ISO 9362) validation and an ISO 3166-1 country registry (see
> [`CHANGELOG.md`](../CHANGELOG.md)).

## Two layers, one direction

`daycry/iban` is built as a zero-dependency core with an optional, CI4-native layer on top. Dependency
flows in **one direction only**: the core never knows about the resolver, and neither the core nor the
resolver knows about CodeIgniter 4.

```
[ CI4 integration ]  Config, Services('iban'/'isoCountries'), iban_helper, spark commands
        |  (thin adapter, no domain logic)
        v
[ Resolver ]  ResolverInterface -> ProviderInterface (NullProvider | DatabaseProvider)
        |     + optional BicProviderInterface (resolveBic); produces BankResult / BankInfo
        v
[ Core ]  IBAN: structural registry (in code) -> normalize/validate/parse/format -> ParsedIban
          BIC:  ISO 3166-1 registry (in code) -> BicValidator/BicParser -> ParsedBic
                + IbanBicCrossChecker (IBAN<->BIC coherence)
          zero dependencies, usable outside CI4, produces ParsedIban / ParsedBic / ValidationResult
```

- **Core** — normalizes, validates, parses and formats IBANs using a structural registry compiled
  into PHP (no database, no network). Produces `ParsedIban` / `ValidationResult`. Since v2.1 it also
  validates and parses **BICs** (ISO 9362) via `BicValidator` / `BicParser` (producing `ParsedBic`),
  cross-checks an IBAN against a BIC via `IbanBicCrossChecker`, and recognises a BIC's country code
  against a compiled **ISO 3166-1 country registry** (`IsoCountryRegistry`, 249 codes — separate from
  the ~78-country IBAN registry because most BIC-issuing countries issue no IBAN). All of this works
  with `php -r` alone; no framework required. A BIC carries no checksum, so BIC "validity" means only
  *well-formed + recognised country*.
- **Resolver** — an optional layer that overlays bank metadata (name, BIC, SEPA reachability) onto a
  parsed IBAN. Pluggable via `ProviderInterface`: `NullProvider` (default, always returns `null`,
  never touches a database) or `DatabaseProvider` (opt-in, CodeIgniter 4 Model + Migration + empty
  Seed). Since v2.1 it can also resolve a bank straight from a BIC (`resolveBic()`) when the provider
  additionally implements the optional, additive `BicProviderInterface` (the default `NullProvider`
  does not, so BIC resolution degrades gracefully to `null`).
- **CI4 integration** — a thin adapter (`Config\Iban`, `Services::iban()`, `iban_helper.php`, spark
  commands) with no domain logic of its own; it only wires the core/resolver into the framework.
  The `iban:update` command drives the v1.1 bank-data importer framework (`ImporterInterface` +
  `ImportRunner`) to populate the `banks` table from official sources.

## The dependency rule, enforced

`src/Contracts`, `src/Core`, `src/DTO`, `src/Enums`, `src/Exceptions`, `src/Registry`,
`src/National`, `src/Resolver`, `src/Import/Importers` and `src/Import/Support` must stay
framework-free: no `codeigniter4/*` requirement, no `CodeIgniter\...` import, anywhere (plus the
individually-guarded files `src/Iban.php`, `src/Import/ImporterRegistry.php` and
`src/Import/ImportReport.php`). Only `src/Config`, `src/Commands`, `src/Models`, `src/Database`,
`src/Providers` (the bank-data `DatabaseProvider`/`CachedProvider`/`IbanComProvider`, **and** the ISO
`DatabaseIsoCountryLoader`), `src/Helpers` and `src/Import/ImportRunner.php` are allowed to depend on
CI4.

This is why the two database-backed loaders live under `src/Providers/` rather than beside their
compiled-PHP counterparts: `DatabaseProvider` (not in `src/Resolver/`) and — new in v2.1 —
`DatabaseIsoCountryLoader` (not in `src/Registry/`, alongside `PhpIsoCountryLoader`). `src/Registry/`
is a framework-free guarded directory, so its CI4-dependent DB overlay has to sit one layer up in the
un-guarded `src/Providers/`; the framework-free `IsoCountryRegistry` / `PhpIsoCountryLoader` /
`IsoCountry` stay in `src/Registry/` and `src/DTO/`. (`Contracts\ImporterInterface`,
`Import\ImportReport` and `Import\ImporterRegistry` likewise stay framework-free; the concrete
importers under `src/Import/Importers/` and the `.xlsx` reader under `src/Import/Support/` fetch and
parse with plain PHP, requiring `ext-mbstring` / `ext-iconv` / `ext-zip` but never CI4.)

This isn't just a convention — `tests/Architecture/CoreIsFrameworkFreeTest.php` scans the guarded
directories for `CodeIgniter\` / `codeigniter4` references and fails the build if either appears,
with two self-tests proving the detector actually detects (not a trivial stub).

## Domain model (implemented — Phase 1)

**Enums** (`src/Enums`):
- `ViolationCode` — backed `string` enum, **16 cases**: the 8 IBAN cases (`Blank`, `TooShort`,
  `UnknownCountry`, `IllegalCharacters`, `BadLength`, `MalformedStructure`, `ChecksumFailed`,
  `NationalCheckFailed`) plus 8 added in v2.1: `BicBlank`, `BicBadLength`, `BicIllegalCharacters`,
  `BicMalformedStructure`, `BicUnknownCountry` (BIC validation), `BicIbanCountryMismatch`,
  `BicIbanBankMismatch` (IBAN↔BIC cross-check), and `NothingToValidate` (combined entry point).
  Additive, but a consumer with an exhaustive `match` must add arms.
- `IbanFormat` — pure enum: `Electronic`, `Print`, `Anonymized`.

**DTOs** (`src/DTO`, all `final readonly`):
- `Violation` — `ViolationCode $code`, `string $messageKey`, `string $message`.
- `ValidationResult` — `bool $valid`, `Violation[] $violations`; `isValid()`, `violations()`,
  `firstViolation(): ?Violation`.
- `ParsedIban` — 9 properties (`countryCode`, `checkDigits`, `bban`, `bankIdentifier`,
  `?branchIdentifier`, `accountNumber`, `?nationalCheckDigit`, `sepaCountry`, `electronic`);
  `format(IbanFormat $f = IbanFormat::Print): string`, `__toString(): string` (returns `$electronic`).
- `ParsedBic` (v2.1) — `bic`, `institutionCode`, `countryCode`, `locationCode`, `?branchCode`;
  `isPrimaryOffice(): bool`, `bic8(): string`, `__toString(): string`.
- `BankInfo` — 12 nullable properties describing a resolved bank entity (output of
  `ProviderInterface`), plus a nullable `resolvedBy` provenance marker.
- `BankResult` — composes a `ParsedIban` with the same nullable bank fields as `BankInfo`, plus
  `isResolved(): bool` (`true` iff any bank field is non-null; output of `ResolverInterface`).
- `IsoCountry` (v2.1) — `alpha2`, `name`, `alpha3`, `numeric` (output of `IsoCountryRegistry`).

**Exceptions** (`src/Exceptions`):
- `IbanException extends \RuntimeException`.
- `InvalidIbanException` — `final`; carries the `ValidationResult` that caused a strict parse failure
  via `result(): ValidationResult`.
- `InvalidBicException` (v2.1) — `final`, extends `IbanException`; the BIC analogue, carrying the
  failing `ValidationResult` via `result()` (thrown only by `BicParser::parse()`).

**Contracts** (`src/Contracts`, all framework-free — **nine**): `ValidatorInterface`, `ParserInterface`,
`ProviderInterface`, `ResolverInterface`, `RegistryLoaderInterface`,
`NationalCheckValidatorInterface` (frozen since the close of Phase 1), `ImporterInterface`
(added in v1.1), plus `BicProviderInterface` (v2.1, optional/additive BIC resolution) and
`IsoCountryLoaderInterface` (v2.1, loads the ISO 3166-1 registry).

## Status

**Feature-complete.** All v1.0 phases shipped: the structural registry (78 countries),
the algorithmic core (`Normalizer`, `Mod97`, `StructureCompiler`, `Validator`, `Parser`,
`Formatter`), national check-digit validation, the resolver + providers + `banks` schema, CI4
integration (`Config\Iban`, `service('iban')`, `iban_helper`, spark commands), and the test
suite/docs. v1.1 added national check-digit validators for **9 countries** (ES/BE/PT/SI/FI/FR/MC/IT/SM),
an optional `CachedProvider`, and the bank-data importer framework; v1.2 grew that to **30 bundled
importers**; v1.4/v2.0 added the opt-in iban.com fallback and `resolvedBy` provenance. **v2.1** adds
**BIC (ISO 9362) validation, parsing, IBAN↔BIC cross-check and BIC-first resolution**, backed by a new
compiled **ISO 3166-1 country registry** (`IsoCountryRegistry`, 249 codes), all framework-free. PHPStan
level 8 and PSR-12 stay clean; see [`CHANGELOG.md`](../CHANGELOG.md) for the current gate numbers.

## References

- Design spec: [`superpowers/specs/2026-07-10-daycry-iban-v1-design.md`](superpowers/specs/2026-07-10-daycry-iban-v1-design.md)
- Roadmap: [`roadmap/2026-07-10-daycry-iban-v1/`](roadmap/2026-07-10-daycry-iban-v1/) —
  `spec.md`, `evaluation.md`, `improvement-plan.md`, `tasks.md` (per-task acceptance criteria and
  progress)
