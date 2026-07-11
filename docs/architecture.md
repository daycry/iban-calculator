# Architecture

> Overview only. Full usage documentation is planned for Phase 8 of the roadmap and does not exist
> yet. Source of truth for anything below: the design spec and the roadmap linked at the bottom.

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

## The dependency rule, enforced

`src/Contracts`, `src/Core`, `src/DTO`, `src/Enums`, `src/Exceptions` and `src/Registry` must stay
framework-free: no `codeigniter4/*` requirement, no `CodeIgniter\...` import, anywhere. Only
`src/Config`, `src/Commands`, `src/Models`, `src/Database`, `src/Providers/DatabaseProvider` and
`src/Helpers` are allowed to depend on CI4.

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

**Contracts** (`src/Contracts`, frozen since the close of Phase 1):
`ValidatorInterface`, `ParserInterface`, `ProviderInterface`, `ResolverInterface`,
`RegistryLoaderInterface`, `NationalCheckValidatorInterface`. All framework-free.

## Current phase

**Phase 1 (Foundations) is complete**: tooling/CI, the domain model above, and all six frozen
contracts. 71 tests / 144 assertions, PHPStan level 8, PSR-12 — all green.

**Not built yet:**
- Phase 2 — structural registry (~84 SWIFT countries: length, BBAN token grammar, bank/branch/
  account/national-check offsets).
- Phase 3 — algorithmic core (`Normalizer`, `Mod97`, `StructureCompiler`, `Validator`, `Parser`,
  `Formatter`).
- Phase 4 — Spanish national check-digit validator (mod-11) + `bin/` registry data generator.
- Phase 5 — `Resolver`, `NullProvider`, optional `DatabaseProvider` (Model + Migration + empty Seed),
  `Iban` facade.
- Phase 6 — CodeIgniter 4 integration (`Config\Iban`, `Services::iban()`, helper, spark commands).
- Phase 7 — cross-cutting test fixtures/infrastructure.
- Phase 8 — full usage documentation.

## References

- Design spec: [`superpowers/specs/2026-07-10-daycry-iban-v1-design.md`](superpowers/specs/2026-07-10-daycry-iban-v1-design.md)
- Roadmap: [`roadmap/2026-07-10-daycry-iban-v1/`](roadmap/2026-07-10-daycry-iban-v1/) —
  `spec.md`, `evaluation.md`, `improvement-plan.md`, `tasks.md` (per-task acceptance criteria and
  progress)
