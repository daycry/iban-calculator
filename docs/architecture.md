# Architecture

> Concise architecture overview. Full usage documentation lives in [`docs/usage.md`](usage.md), and
> the importer subsystem in [`docs/importers.md`](importers.md). The package is feature-complete
> through v1.1 (see [`CHANGELOG.md`](../CHANGELOG.md)).

## Two layers, one direction

`daycry/iban` is built as a zero-dependency core with an optional, CI4-native layer on top. Dependency
flows in **one direction only**: the core never knows about the resolver, and neither the core nor the
resolver knows about CodeIgniter 4.

```
[ CI4 integration ]  Config, Services('iban'), iban_helper, spark commands
        |  (thin adapter, no domain logic)
        v
[ Resolver ]  ResolverInterface -> ProviderInterface (NullProvider | DatabaseProvider)
        |     produces BankResult (composes ParsedIban + nullable bank data)
        v
[ Core ]  structural registry (in code) -> normalize/validate/parse/format
          zero dependencies, usable outside CI4, produces ParsedIban / ValidationResult
```

- **Core** — normalizes, validates, parses and formats IBANs using a structural registry compiled
  into PHP (no database, no network). Produces `ParsedIban` / `ValidationResult`. Works with `php
  -r` alone; no framework required.
- **Resolver** — an optional layer that overlays bank metadata (name, BIC, SEPA reachability) onto a
  parsed IBAN. Pluggable via `ProviderInterface`: `NullProvider` (default, always returns `null`,
  never touches a database) or `DatabaseProvider` (opt-in, CodeIgniter 4 Model + Migration + empty
  Seed).
- **CI4 integration** — a thin adapter (`Config\Iban`, `Services::iban()`, `iban_helper.php`, spark
  commands) with no domain logic of its own; it only wires the core/resolver into the framework.
  The `iban:update` command drives the v1.1 bank-data importer framework (`ImporterInterface` +
  `ImportRunner`) to populate the `banks` table from official sources.

## The dependency rule, enforced

`src/Contracts`, `src/Core`, `src/DTO`, `src/Enums`, `src/Exceptions`, `src/Registry`,
`src/National` and `src/Resolver` must stay framework-free: no `codeigniter4/*` requirement, no
`CodeIgniter\...` import, anywhere. Only `src/Config`, `src/Commands`, `src/Models`, `src/Database`,
`src/Providers/DatabaseProvider`, `src/Helpers` and `src/Import/ImportRunner.php` are allowed to
depend on CI4. (`Contracts\ImporterInterface`, `Import\ImportReport` and `Import\ImporterRegistry`
stay framework-free; the concrete importers under `src/Import/Importers/` fetch and parse with plain
PHP, requiring only `ext-mbstring`.)

This isn't just a convention — `tests/Architecture/CoreIsFrameworkFreeTest.php` scans the guarded
directories for `CodeIgniter\` / `codeigniter4` references and fails the build if either appears,
with two self-tests proving the detector actually detects (not a trivial stub).

## Domain model (implemented — Phase 1)

**Enums** (`src/Enums`):
- `ViolationCode` — backed `string` enum, 8 cases: `Blank`, `TooShort`, `UnknownCountry`,
  `IllegalCharacters`, `BadLength`, `MalformedStructure`, `ChecksumFailed`, `NationalCheckFailed`.
- `IbanFormat` — pure enum: `Electronic`, `Print`, `Anonymized`.

**DTOs** (`src/DTO`, all `final readonly`):
- `Violation` — `ViolationCode $code`, `string $messageKey`, `string $message`.
- `ValidationResult` — `bool $valid`, `Violation[] $violations`; `isValid()`, `violations()`,
  `firstViolation(): ?Violation`.
- `ParsedIban` — 9 properties (`countryCode`, `checkDigits`, `bban`, `bankIdentifier`,
  `?branchIdentifier`, `accountNumber`, `?nationalCheckDigit`, `sepaCountry`, `electronic`);
  `format(IbanFormat $f = IbanFormat::Print): string`, `__toString(): string` (returns `$electronic`).
- `BankInfo` — 12 nullable properties describing a resolved bank entity (output of
  `ProviderInterface`).
- `BankResult` — composes a `ParsedIban` with the same nullable bank fields as `BankInfo`, plus
  `isResolved(): bool` (`true` iff any bank field is non-null; output of `ResolverInterface`).

**Exceptions** (`src/Exceptions`):
- `IbanException extends \RuntimeException`.
- `InvalidIbanException` — `final`; carries the `ValidationResult` that caused a strict parse failure
  via `result(): ValidationResult`.

**Contracts** (`src/Contracts`, all framework-free): `ValidatorInterface`, `ParserInterface`,
`ProviderInterface`, `ResolverInterface`, `RegistryLoaderInterface`,
`NationalCheckValidatorInterface` (frozen since the close of Phase 1), plus `ImporterInterface`
(added in v1.1 for the bank-data importer framework).

## Status

**Feature-complete through v1.1.** All v1.0 phases shipped: the structural registry (78 countries),
the algorithmic core (`Normalizer`, `Mod97`, `StructureCompiler`, `Validator`, `Parser`,
`Formatter`), national check-digit validation, the resolver + providers + `banks` schema, CI4
integration (`Config\Iban`, `service('iban')`, `iban_helper`, spark commands), and the test
suite/docs. v1.1 added national check-digit validators for **9 countries**
(ES/BE/PT/SI/FI/FR/MC/IT/SM), an optional `CachedProvider` (opt-in via `Config\Iban::$cacheTtl`), and
the bank-data importer framework with **5 bundled official-source importers** and a functional
`iban:update` (see [`docs/importers.md`](importers.md)). 860 tests / 1720 assertions, PHPStan level 8,
PSR-12 — all green.

## References

- Design spec: [`superpowers/specs/2026-07-10-daycry-iban-v1-design.md`](superpowers/specs/2026-07-10-daycry-iban-v1-design.md)
- Roadmap: [`roadmap/2026-07-10-daycry-iban-v1/`](roadmap/2026-07-10-daycry-iban-v1/) —
  `spec.md`, `evaluation.md`, `improvement-plan.md`, `tasks.md` (per-task acceptance criteria and
  progress)
