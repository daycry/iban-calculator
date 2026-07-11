# CLAUDE.md

Guidance for Claude Code (and other agents) working in this repository.

## Project overview

`daycry/iban` is an IBAN library for CodeIgniter 4 — validation, parsing, formatting and bank-entity
resolution — that is also usable **standalone**, outside of any framework.

- Composer package name: `daycry/iban`. GitHub repo slug: `daycry/iban-calculator`.
- PHP `^8.3`. CodeIgniter 4 `^4.6` is a **dev-only peer dependency** (`require-dev`), never a hard
  `require` — the core works without CI4 installed at all.
- **Current status: v1.2 is feature-complete.** 1,036 tests / 2,699 assertions green, PHPStan level 8
  clean (`src` + `tests`, with the `codeigniter/phpstan-codeigniter` extension), PSR-12 clean. v1.0's
  8 roadmap phases (`docs/roadmap/2026-07-10-daycry-iban-v1/tasks.md`) were done first: structural
  registry (78 countries), algorithmic core, Spanish national check-digit validator, resolver +
  providers + DB schema, CI4 integration (`Config\Iban`, `service('iban')`, `iban_helper`, 4 spark
  commands), cross-cutting test fixtures, and the doc set. v1.1 (see `CHANGELOG.md`'s `[1.1.0]`
  section) added: 8 more national check-digit validators (ES/BE/PT/SI/FI/FR/MC/IT/SM, EE deliberately
  omitted), an optional resolver cache (`Providers\CachedProvider`, `Config\Iban::$cacheTtl`), a
  bank-data importer framework (`Contracts\ImporterInterface` + `Import\`) with 5 bundled
  official-source importers (OeNB/Bundesbank/SIX/Betaalvereniging/Banco de España) that make
  `iban:update` functional instead of a no-op, `Config\Iban::$defaultFormat`/`$checkNationalByDefault`
  actually being honored, and a new `ext-mbstring` requirement (used by the CSV/fixed-width
  importers). v1.2 (see `CHANGELOG.md`'s `[1.2.0]` section) expanded the importer framework from 5 to
  **30 bundled importers**: 20 more national official-source importers (CZ/GR/SI/SK/BG/MD/PL/AZ/BE/
  HR/LU/MT/HU/NO/GE/IL/UA/KZ/LI/BR) plus a supranational `EpcRegisterImporter` registered five times
  (GB/GI/IE/LV/RO, the SEPA countries whose `bank_code` is a BIC prefix) — 24 of 42 SEPA countries now
  resolve. It also added `Import\Support\XlsxReader` (a minimal read-only `.xlsx` reader, no
  PhpSpreadsheet dependency), a `Resolver` bank-level fallback (`findByBankCode($cc, $bank, null)` when
  an exact IBAN match misses, so branch-carrying IBANs resolve against bank-level-only rows), a
  `SixImporter` fix (now filters `Country === 'CH'`, previously also imported Liechtenstein rows), and
  two new runtime requirements, `ext-iconv` and `ext-zip`.

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
- `src/Contracts` — seven interfaces (`ValidatorInterface`, `ParserInterface`, `ProviderInterface`,
  `ResolverInterface`, `RegistryLoaderInterface`, `NationalCheckValidatorInterface`,
  `ImporterInterface`, added v1.1).
- `src/Core` — `Normalizer`, `Mod97`, `StructureCompiler`, `Validator`, `Parser`, `Formatter`.
- `src/DTO` — `Violation`, `ValidationResult`, `ParsedIban`, `BankInfo`, `BankResult`. All
  `final readonly`.
- `src/Enums` — `ViolationCode` (backed `string` enum, 8 cases), `IbanFormat` (pure enum:
  `Electronic` / `Print` / `Anonymized`).
- `src/Exceptions` — `IbanException` (`extends \RuntimeException`), `InvalidIbanException` (`final`,
  carries the `ValidationResult` that caused a strict parse failure via `result()`).
- `src/Registry` — `Registry`, `PhpRegistryLoader`, `CountryStructure`, plus the raw
  `src/Registry/data/countries.php` data array (78 countries).
- `src/National` — country-specific national check-digit validators: `SpanishNationalCheckValidator`
  (ES, weighted mod-11), `BelgianNationalCheckValidator` (BE), `PortugueseNationalCheckValidator` (PT),
  `SlovenianNationalCheckValidator` (SI), `FinnishNationalCheckValidator` (FI),
  `FrenchNationalCheckValidator` (FR + MC), `ItalianNationalCheckValidator` (IT + SM) — 9 countries as
  of v1.1, wired via `Core\Validator`'s `$nationalValidators` map. EE deliberately has no validator
  (see that map's docblock in `src/Core/Validator.php`).
- `src/Resolver` — `Resolver` (composes `BankResult` from a `ParsedIban` + a `ProviderInterface`
  overlay; v1.2 added a bank-level fallback — `findByBankCode($cc, $bank, null)` when `findByIban()`
  misses — so IBANs carrying a branch segment still resolve against bank-level-only rows); must stay
  usable without CI4, so it is guarded even though `DatabaseProvider` (a `ProviderInterface`
  implementation it can be handed) is not.

**Allowed to depend on CI4 (kept as thin adapters, no domain logic):**
- `src/Config` (`Config\Iban`, `Config\Services`, `Config\Registrar`), `src/Commands` (4 spark
  commands), `src/Models` (`BankModel`), `src/Database` (Migrations/Seeds),
  `src/Providers/DatabaseProvider`, `src/Helpers` (`iban_helper.php`).
- `src/Providers/NullProvider` stays framework-free by design (it is the default, zero-dependency
  provider) even though `Providers/` as a directory is not itself guarded — `DatabaseProvider` lives
  there too and does depend on CI4. `Providers/CachedProvider` (v1.1) also depends on CI4 (a
  `CodeIgniter\Cache\CacheInterface`), same reasoning.
- `src/Import` (v1.1, importer framework; v1.2 expanded it) is a **split directory, not itself
  guarded**: `Contracts/ImporterInterface`, `Import/ImportReport`, `Import/ImporterRegistry`,
  `Import/Support/XlsxReader` (v1.2) and all 30 bundled importers under `Import/Importers/` (including
  the shared `Import/Importers/Concerns/ParsesSixBankMaster` trait) are written framework-free by
  discipline (they fetch via plain `file_get_contents()`/`fgetcsv()`/`SimpleXMLElement`/`ZipArchive`,
  never a CI4 HTTP client) — but `Import/ImportRunner` genuinely depends on CI4 (`Models\BankModel`),
  which is why the whole `Import/` directory isn't in `GUARDED_DIRECTORIES`. Don't assume every file
  under `Import/` is framework-free — check the individual file's own docblock, which states its
  status explicitly. **Known gap**: `tests/Architecture/CoreIsFrameworkFreeTest.php`'s `GUARDED_FILES`
  list still only enumerates v1.1's five importers (plus `ImporterRegistry.php`/`ImportReport.php`);
  the 20 v1.2 national importers, `EpcRegisterImporter.php` and `Import/Support/XlsxReader.php` are
  framework-free in practice but not yet added to that list — a follow-up, not a v1.2 blocker.

**This rule is enforced by a test**, not just convention: `tests/Architecture/CoreIsFrameworkFreeTest.php`
scans `Core/`, `Contracts/`, `DTO/`, `Enums/`, `Exceptions/`, `Registry/`, `National/`, `Resolver/` for
the strings `CodeIgniter\` and `codeigniter4`, and fails if either appears. It also scans a file-level
`GUARDED_FILES` list for the same two files-can't-live-in-a-guarded-directory cases: `src/Iban.php` (the
standalone facade), plus — since v1.1 — the framework-free half of the importer framework:
`Import/ImporterRegistry.php`, `Import/ImportReport.php` and v1.1's 5 `Import/Importers/*Importer.php`
classes (OeNB/Bundesbank/SIX/Betaalvereniging/Banco de España). `Import/ImportRunner.php` is
intentionally **not** in `GUARDED_FILES` (it depends on CI4's `Models\BankModel`). As of v1.2,
`GUARDED_FILES` has **not** been extended to the 20 new national importers, `EpcRegisterImporter.php`
or `Import/Support/XlsxReader.php` — they're written framework-free by the same discipline (see each
file's own docblock), but the guard test doesn't check them yet; treat this as an open follow-up, not
as evidence they depend on CI4. It also carries two self-tests proving the detector isn't a trivial
always-true/always-false stub. If you add a new guarded directory, extend `GUARDED_DIRECTORIES` in that
test; for a single framework-free file outside a guarded directory, extend `GUARDED_FILES` instead.

Before adding any `use CodeIgniter\...` or CI4-only helper/function call, check which directory you're
in. If it's one of the guarded ones, the dependency belongs one layer up instead.

## Build, test, lint

```bash
composer update    # NOT `composer install` — see "No composer.lock" below
composer test        # PHPUnit (tests/) — 1,036 tests / 2,699 assertions
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
  Contracts/    7 interfaces (framework-free), incl. ImporterInterface (v1.1)
  Core/         algorithmic core: Normalizer, Mod97, StructureCompiler, Validator, Parser, Formatter
  DTO/          final readonly value objects: Violation, ValidationResult, ParsedIban, BankInfo, BankResult
  Enums/        ViolationCode (8 cases), IbanFormat (Electronic/Print/Anonymized)
  Exceptions/   IbanException, InvalidIbanException
  Registry/     Registry, PhpRegistryLoader, CountryStructure + data/countries.php (78 countries)
  National/     9 national check-digit validators (v1.1): ES, BE, PT, SI, FI, FR(+MC), IT(+SM)
  Resolver/     Resolver — composes BankResult from ParsedIban + ProviderInterface
  Providers/    NullProvider (framework-free, default), DatabaseProvider (CI4, opt-in),
                 CachedProvider (CI4, opt-in decorator, v1.1)
  Import/       ImportReport, ImporterRegistry (framework-free); ImportRunner (CI4-dependent);
                 Support/XlsxReader (v1.2, framework-free, ext-zip, read-only .xlsx reader);
                 Importers/ — 30 bundled official-source importers (framework-free) + shared
                 Importers/Concerns/ParsesSixBankMaster trait, see docs/importers.md
  Config/       Config\Iban ($cacheTtl added v1.1), Config\Services (service('iban') factory,
                 wraps CachedProvider when cacheTtl>0), Config\Registrar (CI4)
  Commands/     spark commands: iban:validate, iban:parse, iban:resolve, iban:update (CI4;
                 iban:update functional since v1.1, drives ImporterRegistry/ImportRunner)
  Models/       BankModel (CI4)
  Database/     Migrations/ (CreateBanksTable), Seeds/ (BanksSeeder, intentionally empty) (CI4)
  Helpers/      iban_helper.php (CI4)
  Iban.php      the public facade: Daycry\Iban\Iban, composes Validator -> Parser -> Resolver
tests/          mirrors src/ (PHPUnit, CIUnitTestCase where CI4 is involved)
bin/            generate-registry.php — regenerates src/Registry/data/countries.php from an
                 independently authored fact source (annual refresh tooling)
docs/
  api-reference.md                        complete per-symbol API reference (facade/helper/config/
                                            DTOs/enums/exceptions/contracts/registry), code-verified
  usage.md                                facade/helper/command API, ViolationCode table, national
                                            validators, resolve()/caching, Config\Iban reference
  importers.md                            importer framework, iban:update reference, the 30 bundled
                                            importers + coverage matrix, writing a custom importer
  formatting.md                           Electronic/Print/Anonymized format reference
  i18n.md                                 message i18n decision (English core, optional CI4 Language/)
  licensing.md                            data-licensing rationale for the registry
  registry-authoring.md                   methodology for authoring/cross-checking country data
  architecture.md                         concise architecture/status overview
  roadmap.md                              v1.1 shipped summary + v2.0 plans
  superpowers/specs/2026-07-10-daycry-iban-v1-design.md   source design spec
  roadmap/2026-07-10-daycry-iban-v1/     spec.md, evaluation.md, improvement-plan.md, tasks.md
                                           (controller's own task-tracking; do not edit as "docs")
CHANGELOG.md    Keep a Changelog history, starting at [1.0.0], [1.1.0]/[1.2.0] added 2026-07-11
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
- Commands: `iban:validate`, `iban:parse`, `iban:resolve`, `iban:update` (functional since v1.1 —
  lists/runs the 30 bundled importers via `ImporterRegistry`/`ImportRunner`, `--country`/`--source`/
  `--dry-run`/`--file` flags).
- `Config\Iban`: `$provider` (`'null'|'database'|FQCN`), `$defaultFormat`, `$checkNationalByDefault`,
  `$dbGroup`, `$table`, `$cacheTtl` (v1.1, seconds; `0` disables `CachedProvider` wrapping).
- `Contracts\ImporterInterface` (v1.1): `countryCode()`, `sourceId()`, `sourceName()`, `license()`,
  `sourceUrl()`, `rows(?string $localFile = null): iterable` — see `docs/importers.md`.

See `docs/api-reference.md` for the complete per-symbol reference, and `docs/usage.md` for the
task-oriented guide with examples verified against the code.
