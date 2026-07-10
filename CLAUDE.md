# CLAUDE.md

Guidance for Claude Code (and other agents) working in this repository.

## Project overview

`daycry/iban` is an IBAN library for CodeIgniter 4 — validation, parsing, formatting and bank-entity
resolution — that is also usable **standalone**, outside of any framework.

- Composer package name: `daycry/iban`. GitHub repo slug: `daycry/iban-calculator`.
- PHP `^8.3`. CodeIgniter 4 `^4.6` is a **dev-only peer dependency** (`require-dev`), never a hard
  `require` — the core works without CI4 installed at all.
- **Current status: v1.0 is feature-complete.** 686 tests / 1237 assertions green, ~99% coverage,
  PHPStan level 8 clean (`src` + `tests`, with the `codeigniter/phpstan-codeigniter` extension), PSR-12
  clean. All 8 roadmap phases (`docs/roadmap/2026-07-10-daycry-iban-v1/tasks.md`) are done: structural
  registry (78 countries), algorithmic core, Spanish national check-digit validator, resolver +
  providers + DB schema, CI4 integration (`Config\Iban`, `service('iban')`, `iban_helper`, 4 spark
  commands), cross-cutting test fixtures, and this documentation set.

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
- `src/Contracts` — six interfaces (`ValidatorInterface`, `ParserInterface`, `ProviderInterface`,
  `ResolverInterface`, `RegistryLoaderInterface`, `NationalCheckValidatorInterface`).
- `src/Core` — `Normalizer`, `Mod97`, `StructureCompiler`, `Validator`, `Parser`, `Formatter`.
- `src/DTO` — `Violation`, `ValidationResult`, `ParsedIban`, `BankInfo`, `BankResult`. All
  `final readonly`.
- `src/Enums` — `ViolationCode` (backed `string` enum, 8 cases), `IbanFormat` (pure enum:
  `Electronic` / `Print` / `Anonymized`).
- `src/Exceptions` — `IbanException` (`extends \RuntimeException`), `InvalidIbanException` (`final`,
  carries the `ValidationResult` that caused a strict parse failure via `result()`).
- `src/Registry` — `Registry`, `PhpRegistryLoader`, `CountryStructure`, plus the raw
  `src/Registry/data/countries.php` data array (78 countries).
- `src/National` — country-specific national check-digit validators, e.g.
  `SpanishNationalCheckValidator` (mod-11, ES-only in v1.0).
- `src/Resolver` — `Resolver` (composes `BankResult` from a `ParsedIban` + a `ProviderInterface`
  overlay); must stay usable without CI4, so it is guarded even though `DatabaseProvider` (a
  `ProviderInterface` implementation it can be handed) is not.

**Allowed to depend on CI4 (kept as thin adapters, no domain logic):**
- `src/Config` (`Config\Iban`, `Config\Services`, `Config\Registrar`), `src/Commands` (4 spark
  commands), `src/Models` (`BankModel`), `src/Database` (Migrations/Seeds),
  `src/Providers/DatabaseProvider`, `src/Helpers` (`iban_helper.php`).
- `src/Providers/NullProvider` stays framework-free by design (it is the default, zero-dependency
  provider) even though `Providers/` as a directory is not itself guarded — `DatabaseProvider` lives
  there too and does depend on CI4.

**This rule is enforced by a test**, not just convention: `tests/Architecture/CoreIsFrameworkFreeTest.php`
scans `Core/`, `Contracts/`, `DTO/`, `Enums/`, `Exceptions/`, `Registry/`, `National/`, `Resolver/` for
the strings `CodeIgniter\` and `codeigniter4`, and fails if either appears. It also carries two
self-tests proving the detector isn't a trivial always-true/always-false stub. If you add a new
guarded directory, extend `GUARDED_DIRECTORIES` in that test.

Before adding any `use CodeIgniter\...` or CI4-only helper/function call, check which directory you're
in. If it's one of the guarded ones, the dependency belongs one layer up instead.

## Build, test, lint

```bash
composer update    # NOT `composer install` — see "No composer.lock" below
composer test        # PHPUnit (tests/) — 686 tests / 1237 assertions
composer analyze      # PHPStan, level 8, paths: src + tests (phpstan.neon)
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
  Contracts/    interfaces (framework-free)
  Core/         algorithmic core: Normalizer, Mod97, StructureCompiler, Validator, Parser, Formatter
  DTO/          final readonly value objects: Violation, ValidationResult, ParsedIban, BankInfo, BankResult
  Enums/        ViolationCode (8 cases), IbanFormat (Electronic/Print/Anonymized)
  Exceptions/   IbanException, InvalidIbanException
  Registry/     Registry, PhpRegistryLoader, CountryStructure + data/countries.php (78 countries)
  National/     national check-digit validators, e.g. SpanishNationalCheckValidator (mod-11)
  Resolver/     Resolver — composes BankResult from ParsedIban + ProviderInterface
  Providers/    NullProvider (framework-free, default), DatabaseProvider (CI4, opt-in)
  Config/       Config\Iban, Config\Services (service('iban') factory), Config\Registrar (CI4)
  Commands/     spark commands: iban:validate, iban:parse, iban:resolve, iban:update (CI4)
  Models/       BankModel (CI4)
  Database/     Migrations/ (CreateBanksTable), Seeds/ (BanksSeeder, intentionally empty) (CI4)
  Helpers/      iban_helper.php (CI4)
  Iban.php      the public facade: Daycry\Iban\Iban, composes Validator -> Parser -> Resolver
tests/          mirrors src/ (PHPUnit, CIUnitTestCase where CI4 is involved)
bin/            generate-registry.php — regenerates src/Registry/data/countries.php from an
                 independently authored fact source (annual refresh tooling)
docs/
  usage.md                                facade/helper/command API, ViolationCode table, resolve()
  formatting.md                           Electronic/Print/Anonymized format reference
  i18n.md                                 message i18n decision (English core, optional CI4 Language/)
  licensing.md                            data-licensing rationale for the registry
  registry-authoring.md                   methodology for authoring/cross-checking country data
  architecture.md                         concise architecture/status overview
  roadmap.md                              v1.1 / v2.0 plans
  superpowers/specs/2026-07-10-daycry-iban-v1-design.md   source design spec
  roadmap/2026-07-10-daycry-iban-v1/     spec.md, evaluation.md, improvement-plan.md, tasks.md
                                           (controller's own task-tracking; do not edit as "docs")
CHANGELOG.md    Keep a Changelog history, starting at [1.0.0]
```

## Coding standards

- PHP `^8.3`, `declare(strict_types=1)` in every file.
- PSR-12 via PHP-CS-Fixer (`.php-cs-fixer.dist.php`): short array syntax, alpha-ordered imports, no
  unused imports, single quotes, trailing commas in multiline.
- PHPStan level 8 (`phpstan.neon`), paths: `src` + `tests`. Keep it clean — no baseline suppressions
  without a strong reason.
- Domain DTOs are `final readonly class` with promoted constructor properties; enums are backed
  (`string`) when they carry a stable external value (`ViolationCode`), pure otherwise (`IbanFormat`).
- Method/class signatures for the public API are **verbatim from the design spec**
  (`docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md`) — don't improvise names or parameter
  order; if a signature seems wrong, raise it rather than silently diverging.
- TDD is the working method for this codebase: failing test → minimal implementation → green → commit.
- Validation messages (`Violation::$message`) are hardcoded English in the framework-free core — see
  `docs/i18n.md` for the rationale and how a CI4 app can translate them.
- The structural registry (`src/Registry/data/countries.php`) is independently authored from public
  facts, never copied from the SWIFT IBAN Registry / globalcitizen / Wikipedia — see
  `docs/registry-authoring.md` and `docs/licensing.md` before touching country data.

## Public API surface (for quick reference)

- Facade `Daycry\Iban\Iban` (also the default `service('iban')` return type): `validate(string|ParsedIban $iban, bool $checkNational = false): ValidationResult`,
  `isValid(string|ParsedIban $iban): bool`, `normalize(string $iban): string`, `parse(string $iban): ParsedIban`
  (throws `InvalidIbanException`), `tryParse(string $iban): ?ParsedIban`,
  `format(string|ParsedIban $iban, IbanFormat $f = IbanFormat::Print): string`,
  `resolve(string|ParsedIban $iban): BankResult`, plus sub-service accessors `validator()`, `parser()`,
  `resolver()`.
- Helper (`helper('iban')`): `iban_validate()`, `iban_is_valid()`, `iban_parse()`, `iban_format()`,
  `iban_resolve()`, `bank_name()`, `bank_bic()`, `iban_country()`, `iban_valid()`.
- Commands: `iban:validate`, `iban:parse`, `iban:resolve`, `iban:update` (documented no-op in v1.0 —
  real bank-data importers are deferred to v1.1).
- `Config\Iban`: `$provider` (`'null'|'database'|FQCN`), `$defaultFormat`, `$checkNationalByDefault`,
  `$dbGroup`, `$table`.

See `docs/usage.md` for the full reference with examples verified against the code.
