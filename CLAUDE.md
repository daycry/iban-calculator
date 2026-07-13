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
  two new runtime requirements, `ext-iconv` and `ext-zip`. Since then: v1.3 added `iban:publish`; v1.4
  added the opt-in iban.com fallback (`Config\Iban::$ibanComApiKey`, `Providers\IbanComProvider` +
  `ChainProvider`); v2.0 made `$cacheTtl` `?int` (BREAKING — `null` now disables, `0` = never-expires)
  and added `BankInfo::$resolvedBy` provenance; **v2.1** (see `CHANGELOG.md`'s `[2.1.0]`) added **BIC
  (ISO 9362) validation/parsing/IBAN↔BIC cross-check/BIC-first resolution** and a framework-free **ISO
  3166-1 country registry** (249 codes) to back it. The specific test/assertion counts below predate
  these releases — trust `composer test`'s output, not the number.

## Layered architecture — the unidirectional dependency rule

The package is split into a framework-free core and a thin CodeIgniter 4 adapter, with dependencies
flowing **one way only**:

```
[ CI4 integration ]  Config, Services('iban'/'isoCountries'), iban_helper, spark commands
        |  (thin adapter, no domain logic)
        v
[ Resolver ]  ResolverInterface -> ProviderInterface (NullProvider | DatabaseProvider)
        |     + optional BicProviderInterface (resolveBic); produces BankResult / BankInfo
        v
[ Core ]  IBAN: structural registry -> normalize/validate/parse/format -> ParsedIban
          BIC (v2.1): ISO 3166-1 registry -> BicValidator/BicParser -> ParsedBic + IbanBicCrossChecker
          zero dependencies, usable outside CI4, produces ParsedIban / ParsedBic / ValidationResult
```

**Framework-free — must never import `codeigniter4/*` or reference `CodeIgniter\`:**
- `src/Contracts` — nine interfaces (`ValidatorInterface`, `ParserInterface`, `ProviderInterface`,
  `ResolverInterface`, `RegistryLoaderInterface`, `NationalCheckValidatorInterface`,
  `ImporterInterface` (v1.1), `BicProviderInterface` (v2.1, optional/additive BIC resolution),
  `IsoCountryLoaderInterface` (v2.1)).
- `src/Core` — `Normalizer`, `Mod97`, `StructureCompiler`, `Validator`, `Parser`, `Formatter`; plus
  (v2.1) `BicValidator`, `BicParser`, `IbanBicCrossChecker` (BIC has no checksum — "valid" = well-formed
  + recognised ISO country; the cross-check compares the *bank* only for the 19 4-letter-alpha-bank-code
  countries, derived at runtime from the registry).
- `src/DTO` — `Violation`, `ValidationResult`, `ParsedIban`, `ParsedBic` (v2.1), `BankInfo`,
  `BankResult`, `IsoCountry` (v2.1). All `final readonly`.
- `src/Enums` — `ViolationCode` (backed `string` enum, 16 cases: 8 IBAN + 5 BIC + 2 cross-check +
  `NothingToValidate`, the latter 8 added v2.1), `IbanFormat` (pure enum:
  `Electronic` / `Print` / `Anonymized`).
- `src/Exceptions` — `IbanException` (`extends \RuntimeException`), `InvalidIbanException` (`final`,
  carries the `ValidationResult` via `result()`), `InvalidBicException` (v2.1, `final`, same shape —
  thrown only by `BicParser::parse()`).
- `src/Registry` — `Registry`, `PhpRegistryLoader`, `CountryStructure` + `data/countries.php` (78
  countries); plus (v2.1) `IsoCountryRegistry`, `PhpIsoCountryLoader` + `data/iso_countries.php` (249
  officially assigned ISO 3166-1 codes, independently authored — used by BIC validation, which must
  recognise every BIC-issuing country, not just the ~78 IBAN ones).
- `src/National` — country-specific national check-digit validators: `SpanishNationalCheckValidator`
  (ES, weighted mod-11), `BelgianNationalCheckValidator` (BE), `PortugueseNationalCheckValidator` (PT),
  `SlovenianNationalCheckValidator` (SI), `FinnishNationalCheckValidator` (FI),
  `FrenchNationalCheckValidator` (FR + MC), `ItalianNationalCheckValidator` (IT + SM) — 9 countries as
  of v1.1, wired via `Core\Validator`'s `$nationalValidators` map. EE deliberately has no validator
  (see that map's docblock in `src/Core/Validator.php`).
- `src/Resolver` — `Resolver` (composes `BankResult` from a `ParsedIban` + a `ProviderInterface`
  overlay; v1.2 added a bank-level fallback — `findByBankCode($cc, $bank, null)` when `findByIban()`
  misses — so IBANs carrying a branch segment still resolve against bank-level-only rows; v2.1 added
  `resolveBic()`, consulting the provider only when it implements `BicProviderInterface`); must stay
  usable without CI4, so it is guarded even though `DatabaseProvider` (a `ProviderInterface`
  implementation it can be handed) is not.

**Allowed to depend on CI4 (kept as thin adapters, no domain logic):**
- `src/Config` (`Config\Iban`, `Config\Services` — now also `service('isoCountries')`, `Config\Registrar`),
  `src/Commands` (6 spark commands, incl. `iban:bic` v2.1), `src/Models` (`BankModel`, `IsoCountryModel`
  v2.1), `src/Database` (Migrations `CreateBanksTable`/`CreateIsoCountriesTable`, Seeds
  `BanksSeeder`/`IsoCountriesSeeder`), `src/Providers/DatabaseProvider`, `src/Helpers` (`iban_helper.php`).
- `src/Providers/NullProvider` stays framework-free by design (it is the default, zero-dependency
  provider) even though `Providers/` as a directory is not itself guarded — `DatabaseProvider` lives
  there too and does depend on CI4. `Providers/CachedProvider` (v1.1) also depends on CI4 (a
  `CodeIgniter\Cache\CacheInterface`), same reasoning. `Providers/DatabaseIsoCountryLoader` (v2.1, the
  DB-backed `IsoCountryLoaderInterface`) lives here — NOT beside `PhpIsoCountryLoader` in the guarded
  `src/Registry/` — precisely because it depends on CI4 (`Models\IsoCountryModel`).
- `src/Import` (v1.1, importer framework; v1.2 expanded it) is a **split directory**: the `Import/Importers/`
  and `Import/Support/` subtrees ARE guarded recursively (all 30 bundled importers, the shared
  `Import/Importers/Concerns/ParsesSixBankMaster` trait, and `Import/Support/XlsxReader`), and
  `Import/ImporterRegistry` + `Import/ImportReport` are guarded as individual files — all framework-free
  (they fetch via plain `file_get_contents()`/`fgetcsv()`/`SimpleXMLElement`/`ZipArchive`, never a CI4
  HTTP client). Only `Import/ImportRunner` genuinely depends on CI4 (`Models\BankModel`), which is why
  the top-level `Import/` directory isn't guarded as a whole. Check the individual file's own docblock,
  which states its status explicitly.

**This rule is enforced by a test**, not just convention: `tests/Architecture/CoreIsFrameworkFreeTest.php`
scans its `GUARDED_DIRECTORIES` — `Core/`, `Contracts/`, `DTO/`, `Enums/`, `Exceptions/`, `Registry/`,
`National/`, `Resolver/`, `Import/Importers/`, `Import/Support/` — for the strings `CodeIgniter\` and
`codeigniter4`, and fails if either appears. It also scans a file-level `GUARDED_FILES` list for the
files-can't-live-in-a-guarded-directory cases: `src/Iban.php` (the standalone facade),
`Import/ImporterRegistry.php` and `Import/ImportReport.php` (the framework-free files living directly
under the un-guarded `Import/`). `Import/ImportRunner.php` is intentionally **not** guarded (it depends
on CI4's `Models\BankModel`). The v2.1 additions need no guard-list change: the new `Core/`, `DTO/`,
`Enums/`, `Exceptions/`, `Contracts/` and `Registry/` files are all covered by those already-guarded
directories, and `DatabaseIsoCountryLoader` correctly lives in the un-guarded `src/Providers/`. It also
carries two self-tests proving the detector isn't a trivial always-true/always-false stub. If you add a
new guarded directory, extend `GUARDED_DIRECTORIES`; for a single framework-free file outside a guarded
directory, extend `GUARDED_FILES`.

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
  Contracts/    9 interfaces (framework-free): +BicProviderInterface, IsoCountryLoaderInterface (v2.1)
  Core/         Normalizer, Mod97, StructureCompiler, Validator, Parser, Formatter; +BicValidator,
                 BicParser, IbanBicCrossChecker (v2.1)
  DTO/          final readonly VOs: Violation, ValidationResult, ParsedIban, ParsedBic (v2.1),
                 BankInfo, BankResult, IsoCountry (v2.1)
  Enums/        ViolationCode (16 cases: 8 IBAN + 8 BIC/cross-check/combined v2.1), IbanFormat
  Exceptions/   IbanException, InvalidIbanException, InvalidBicException (v2.1)
  Registry/     Registry, PhpRegistryLoader, CountryStructure + data/countries.php (78 countries);
                 IsoCountryRegistry, PhpIsoCountryLoader + data/iso_countries.php (249 ISO codes, v2.1)
  National/     9 national check-digit validators (v1.1): ES, BE, PT, SI, FI, FR(+MC), IT(+SM)
  Resolver/     Resolver — composes BankResult from ParsedIban + ProviderInterface; resolveBic() (v2.1)
  Providers/    NullProvider (framework-free, default), DatabaseProvider (CI4; +findByBic v2.1),
                 CachedProvider/ChainProvider/IbanComProvider (CI4; +findByBic v2.1),
                 DatabaseIsoCountryLoader (CI4, opt-in ISO source, v2.1)
  Import/       ImportReport, ImporterRegistry (framework-free); ImportRunner (CI4-dependent);
                 Support/XlsxReader (v1.2, framework-free, ext-zip, read-only .xlsx reader);
                 Importers/ — 30 bundled official-source importers (framework-free) + shared
                 Importers/Concerns/ParsesSixBankMaster trait, see docs/importers.md
  Config/       Config\Iban (+$isoCountrySource/$isoCountryTable v2.1), Config\Services
                 (service('iban') passes IsoCountryRegistry; +service('isoCountries') v2.1), Config\Registrar
  Commands/     spark: iban:validate (+--bic v2.1), iban:parse, iban:resolve, iban:update,
                 iban:publish, iban:bic (v2.1) (CI4)
  Models/       BankModel (+findByBic v2.1), IsoCountryModel (v2.1) (CI4)
  Database/     Migrations/ (CreateBanksTable, CreateIsoCountriesTable v2.1), Seeds/ (BanksSeeder
                 empty, IsoCountriesSeeder v2.1) (CI4)
  Helpers/      iban_helper.php — 16 fns (+7 BIC/combined v2.1) (CI4)
  Iban.php      public facade Daycry\Iban\Iban: Validator->Parser->Resolver + BicValidator->BicParser
tests/          mirrors src/ (PHPUnit, CIUnitTestCase where CI4 is involved)
bin/            generate-registry.php — regenerates src/Registry/data/countries.php from an
                 independently authored fact source (annual refresh tooling)
docs/
  api-reference.md                        complete per-symbol API reference (facade/helper/config/
                                            DTOs/enums/exceptions/contracts/registry), code-verified
  usage.md                                facade/helper/command API, ViolationCode table, national
                                            validators, BIC/SWIFT validation, resolve()/caching, Config\Iban
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
CHANGELOG.md    Keep a Changelog history, [1.0.0] … [2.1.0] (2.1.0 = BIC/SWIFT + ISO 3166-1 registry)
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

- Facade `Daycry\Iban\Iban` (also the default `service('iban')` return type). Constructor (v2.1) gained
  a 3rd trailing, defaulted param: `__construct(Registry = new Registry(), ProviderInterface = new NullProvider(), IsoCountryRegistry = new IsoCountryRegistry())`.
  IBAN: `validate(string|ParsedIban $iban, bool $checkNational = false): ValidationResult`,
  `isValid(...): bool`, `normalize(string): string`, `parse(string): ParsedIban` (throws
  `InvalidIbanException`), `tryParse(string): ?ParsedIban`,
  `format(string|ParsedIban, IbanFormat = IbanFormat::Print): string`,
  `resolve(string|ParsedIban): BankResult`. BIC (v2.1):
  `validateBic(string|ParsedBic): ValidationResult`, `isValidBic(...): bool`, `normalizeBic(string): string`,
  `parseBic(string): ParsedBic` (throws `InvalidBicException`), `tryParseBic(string): ?ParsedBic`,
  `validateIbanAndBic(?string $iban, ?string $bic): ValidationResult`, `resolveBic(string|ParsedBic): ?BankInfo`.
  Sub-service accessors: `validator()`, `parser()`, `resolver()`, `bicValidator()`, `bicParser()`.
- Helper (`helper('iban')`), 16 fns: IBAN — `iban_validate()`, `iban_is_valid()`, `iban_parse()`,
  `iban_format()`, `iban_resolve()`, `bank_name()`, `bank_bic()`, `iban_country()`, `iban_valid()`;
  BIC (v2.1) — `bic_validate()`, `bic_is_valid()`, `bic_parse()`, `bic_format()`, `bic_resolve()`,
  `bic_bank_name()`, `iban_bic_validate()`.
- Commands (6): `iban:validate` (v2.1 `--bic=<bic>`, `<iban>` then optional), `iban:parse`,
  `iban:resolve`, `iban:update` (lists/runs the 30 bundled importers via `ImporterRegistry`/`ImportRunner`,
  `--all`/`--country`/`--source`/`--dry-run`/`--file`), `iban:publish`, `iban:bic <bic> [--json]` (v2.1).
- `Config\Iban`: `$provider` (`'null'|'database'|FQCN`), `$defaultFormat`, `$checkNationalByDefault`,
  `$dbGroup`, `$table`, `$cacheTtl` (`?int`; `null` disables caching, `0` = never-expires),
  `$ibanComApiKey`/`$ibanComTimeout`, and (v2.1) `$isoCountrySource` (`'php'|'database'`) / `$isoCountryTable`.
- `Contracts\ImporterInterface` (v1.1): `countryCode()`, `sourceId()`, `sourceName()`, `license()`,
  `sourceUrl()`, `rows(?string $localFile = null): iterable` — see `docs/importers.md`.
- `Contracts\BicProviderInterface` (v2.1, optional/additive): `findByBic(string $bic): ?BankInfo`.
- `Contracts\IsoCountryLoaderInterface` (v2.1): `load(): array` — see `docs/api-reference.md`.

See `docs/api-reference.md` for the complete per-symbol reference, and `docs/usage.md` for the
task-oriented guide with examples verified against the code.
