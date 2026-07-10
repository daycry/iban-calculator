# CLAUDE.md

Guidance for Claude Code (and other agents) working in this repository.

## Project overview

`daycry/iban` is an IBAN library for CodeIgniter 4 — validation, parsing, formatting and bank-entity
resolution — that is also usable **standalone**, outside of any framework. It is a **greenfield**
project currently in v1.0 development.

- Composer package name: `daycry/iban`. GitHub repo slug: `daycry/iban-calculator`.
- PHP `^8.3`. CodeIgniter 4 `^4.6` is a **dev-only peer dependency** (`require-dev`), never a hard
  `require` — the core must work without CI4 installed at all.
- Current status: **Phase 1 (Foundations) is complete.** 71 tests / 144 assertions green, PHPStan
  level 8 clean, PSR-12 clean. Everything from the structural country registry onward (Phases 2–8)
  is not yet implemented. See "Current status" below before assuming any feature exists.

## Layered architecture — the unidirectional dependency rule

The package is split into a framework-free core and a thin CodeIgniter 4 adapter, with dependencies
flowing **one way only**:

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

**Framework-free — must never import `codeigniter4/*` or reference `CodeIgniter\`:**
- `src/Contracts` — the six interfaces (`ValidatorInterface`, `ParserInterface`, `ProviderInterface`,
  `ResolverInterface`, `RegistryLoaderInterface`, `NationalCheckValidatorInterface`). Frozen since the
  close of Phase 1.
- `src/Core` — `Normalizer`, `Mod97`, `StructureCompiler`, `Validator`, `Parser`, `Formatter` (Phase 3,
  not yet built).
- `src/DTO` — `Violation`, `ValidationResult`, `ParsedIban`, `BankInfo`, `BankResult`. All
  `final readonly`.
- `src/Enums` — `ViolationCode` (backed `string` enum, 8 cases), `IbanFormat` (pure enum:
  `Electronic` / `Print` / `Anonymized`).
- `src/Exceptions` — `IbanException` (`extends \RuntimeException`), `InvalidIbanException` (`final`,
  carries the `ValidationResult` that caused a strict parse failure via `result()`).
- `src/Registry` — the structural country registry (Phase 2, not yet built). Also framework-free.

**Allowed to depend on CI4 (kept as thin adapters, no domain logic):**
- `src/Config`, `src/Commands`, `src/Models`, `src/Database` (Migrations/Seeds),
  `src/Providers/DatabaseProvider`, `src/Helpers`.
- `src/Providers/NullProvider` stays framework-free (it is the default, zero-dependency provider).

**This rule is enforced by a test**, not just convention: `tests/Architecture/CoreIsFrameworkFreeTest.php`
scans `Core/`, `Contracts/`, `DTO/`, `Enums/`, `Exceptions/` for the strings `CodeIgniter\` and
`codeigniter4`, and fails if either appears. It also carries two self-tests proving the detector isn't
a trivial always-true/always-false stub. If you add a new guarded directory (e.g. once `src/Registry`
gains real code in Phase 2), extend `GUARDED_DIRECTORIES` in that test.

Before adding any `use CodeIgniter\...` or CI4-only helper/function call, check which directory you're
in. If it's one of the guarded ones, the dependency belongs one layer up instead.

## Build, test, lint

```bash
composer update    # NOT `composer install` — see "No composer.lock" below
composer test        # PHPUnit (tests/)
composer analyze      # PHPStan, level 8, paths: src (phpstan.neon)
composer cs            # PHP-CS-Fixer, PSR-12 + strict_types, dry-run (add `fix` locally to apply)
```

CI runs these same three scripts across a PHP 8.3/8.4 matrix (`.github/workflows/phpunit.yml`,
`phpstan.yml`, `code-style.yml`), plus a CodeQL scan of the workflow files themselves
(`codeql.yml`, `language: actions` — PHP has no native CodeQL analyzer).

## No `composer.lock` convention

`composer.lock` is intentionally **gitignored** (see `.gitignore`). This is a library, not an
application: consumers resolve their own dependency graph, and CI always runs `composer update`
(never `composer install`) so the test matrix reflects the actual version constraints in
`composer.json`, not a pinned snapshot. Composer cache keys in CI hash `composer.json`, not
`composer.lock`. `config.platform.php` is pinned to `8.3.20` in `composer.json` for reproducible
platform resolution despite the missing lock file.

## Where things live

```
src/
  Contracts/    interfaces (framework-free, frozen)
  Core/         algorithmic core: Normalizer, Mod97, StructureCompiler, Validator, Parser, Formatter (Phase 3)
  DTO/          final readonly value objects
  Enums/        ViolationCode, IbanFormat
  Exceptions/   IbanException, InvalidIbanException
  Registry/     structural country registry + data/countries.php (Phase 2)
  National/     national check-digit validators, e.g. SpanishNationalCheckValidator (Phase 4)
  Resolver/     Resolver (Phase 5)
  Providers/    NullProvider (framework-free), DatabaseProvider (CI4) (Phase 5)
  Config/       Config\Iban (CI4) (Phase 6)
  Commands/     spark commands: iban:validate, iban:parse, iban:resolve, iban:update (Phase 6)
  Models/       BankModel (CI4) (Phase 5)
  Database/     Migrations/, Seeds/ for the optional `banks` table (Phase 5)
  Helpers/      iban_helper.php (CI4) (Phase 6)
tests/          mirrors src/ (PHPUnit, CIUnitTestCase where CI4 is involved)
bin/            registry data generator (Phase 4, not yet built)
docs/
  architecture.md                        concise architecture/status overview
  superpowers/specs/2026-07-10-daycry-iban-v1-design.md   source design spec
  roadmap/2026-07-10-daycry-iban-v1/     spec.md, evaluation.md, improvement-plan.md, tasks.md
```

## Current status

Phase 1 (Foundations) is complete: tooling/CI, the domain model (enums, DTOs, exceptions) and all six
frozen contracts. Phases 2–8 (structural registry, algorithmic core, national checks + data generator,
resolver/providers/DB schema, CI4 integration, cross-cutting tests, full docs) are still to be built.
Check `docs/roadmap/2026-07-10-daycry-iban-v1/tasks.md` for the authoritative, per-task checklist
before assuming any given class or feature exists — don't infer implementation status from the spec
alone, since the spec describes the target design, not the current state.

## Coding standards

- PHP `^8.3`, `declare(strict_types=1)` in every file.
- PSR-12 via PHP-CS-Fixer (`.php-cs-fixer.dist.php`): short array syntax, alpha-ordered imports, no
  unused imports, single quotes, trailing commas in multiline.
- PHPStan level 8 (`phpstan.neon`), paths: `src`. Keep it clean — no baseline suppressions without a
  strong reason.
- Domain DTOs are `final readonly class` with promoted constructor properties; enums are backed
  (`string`) when they carry a stable external value (`ViolationCode`), pure otherwise (`IbanFormat`).
- Method/class signatures for anything already specified are **verbatim from the design spec**
  (`docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md` §4–§7) — don't improvise names or
  parameter order for in-scope work; if a signature seems wrong, raise it rather than silently
  diverging.
- TDD is the working method for this codebase: failing test → minimal implementation → green → commit.
  See `docs/roadmap/2026-07-10-daycry-iban-v1/tasks.md` for the per-task acceptance criteria that
  drive the tests.
