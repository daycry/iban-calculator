# Changelog

All notable changes to `daycry/iban` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-07-12

### Changed

- **BREAKING — `Config\Iban::$cacheTtl` is now `?int` (default `null`).** In CodeIgniter's cache a TTL
  of `0` means **"never expires"** (`FileHandler` stores no expiry when `ttl <= 0`; `RedisHandler` only
  calls `expireAt()` when `$ttl !== 0`). This package, however, used `0` to mean **"caching
  disabled"** — a semantic collision that also left **no way to configure a never-expiring cache**.
  The values now are:
  - `null` (the new default) → **caching disabled** — the provider is left unwrapped (this is what the
    old `0` did).
  - `0` → **cache never expires** (forwarded verbatim to CI4's cache).
  - `> 0` → TTL in **seconds**.

  **Migration:** if you explicitly set `$cacheTtl = 0` to *disable* caching (including in a config
  published by `iban:publish`, which copied the old `0` default), change it to **`null`** — otherwise
  you will now cache **forever**. Leaving the property untouched at its new `null` default preserves
  the previous behavior.

  **Caveat when using `0`:** `CachedProvider` caches **misses** as well as hits, so a never-expiring
  cache will remember a "not found" forever. After running `iban:update` (which seeds new bank rows),
  run `php spark cache:clear`, or previously-missed IBANs will keep resolving to nothing.

### Added

- **`resolvedBy` provenance on `BankInfo` / `BankResult`**: identifies *which provider* answered a
  `resolve()` — `'database'` (local `banks` table), `'iban.com'` (the remote API fallback), or a
  custom provider's own id. This is distinct from `sourceId`, which identifies the *dataset* the data
  came from (`'epc'`, `'bde'`, …). It flows unchanged through `ChainProvider` and `CachedProvider` (a
  cached hit still reports its original origin), and is exposed by `iban:resolve` (table and `--json`).
  `isResolved()` semantics are unchanged — `resolvedBy` is metadata and never makes a result count as
  resolved on its own.

  ```php
  $r = service('iban')->resolve($iban);
  $r->resolvedBy;   // 'database' | 'iban.com' | <custom>
  $r->sourceId;     // 'epc' | 'bde' | 'iban.com' | ...
  ```

[2.0.0]: https://github.com/daycry/iban-calculator/compare/1.4.0...2.0.0

## [1.4.0] - 2026-07-11

### Added

- **Optional iban.com API fallback.** New `Config\Iban::$ibanComApiKey` (default `''`; set it via
  `.env` as `iban.ibanComApiKey`). When non-empty, `Config\Services::iban()` chains a new
  `Providers\IbanComProvider` **after** the primary provider (via `Providers\ChainProvider`), so an
  IBAN the local `banks` table can't resolve is looked up through the iban.com Validation API
  (`POST /clients/api/v4/iban/`; `bank_data`/`sepa_data` → `BankInfo`). This lets resolution reach
  countries with no bundled importer (e.g. IT/Italy, whose ABI/CAB directory is commercial). Opt-in
  only — with no key, nothing is sent anywhere and behavior is identical. When `$cacheTtl > 0` the
  chain is wrapped in `CachedProvider`, so repeated lookups don't re-hit the paid API. The provider
  **never throws**: any network/timeout/HTTP/parse failure (or an empty result) degrades to an
  unresolved `BankResult`. Sending IBANs to iban.com is a privacy/cost trade-off the operator opts
  into by configuring the key.
- **`Providers\ChainProvider`**: composes `ProviderInterface`s in order and returns the first non-null
  result — the mechanism behind the local-then-iban.com fallback.

[1.4.0]: https://github.com/daycry/iban-calculator/compare/1.3.1...1.4.0

## [1.3.1] - 2026-07-11

### Fixed

- **A published `app/Config/Iban.php` is now actually honored.** The package resolved its config by the
  fully-qualified class name (`config(Daycry\Iban\Config\Iban::class)`), but CI4's `Factories` only
  prefers the app's `Config\` namespace for a *non-namespaced* alias — so a published override
  (`Config\Iban extends \Daycry\Iban\Config\Iban`, as written by `iban:publish` in 1.3.0) was silently
  ignored and the package default always won. Every config read — `service('iban')`, `iban:update`,
  `iban:validate`, and the helper's `$defaultFormat` / `$checkNationalByDefault` fallbacks — now
  resolves through a single typed `Config\Services::config()` that requests the **short name** `'Iban'`,
  so a published override wins (via CI4's `preferApp`) while the package default still applies when
  nothing is published. (`.env` `iban.*` overrides were never affected and continue to work.) Added a
  regression test.

[1.3.1]: https://github.com/daycry/iban-calculator/compare/1.3.0...1.3.1

## [1.3.0] - 2026-07-11

### Added

- **`iban:publish` command**: publishes the package config into the consuming app as
  `app/Config/Iban.php` — the idiomatic CodeIgniter "publish a config" pattern (à la
  `daycry/auth`/Shield). The published class is `namespace Config; class Iban extends
  \Daycry\Iban\Config\Iban`, so `config()`/`service('iban')` pick it up automatically and it stays
  forward-compatible: any property the app doesn't override keeps inheriting the package default
  (including options added in future versions), and it's a real type-checked subclass rather than a
  drift-prone copy. Refuses to overwrite an existing `app/Config/Iban.php` unless `--force` is passed.

[1.3.0]: https://github.com/daycry/iban-calculator/compare/1.2.1...1.3.0

## [1.2.1] - 2026-07-11

### Fixed

- **PHP 8.4+ compatibility (`iban:update` memory exhaustion)**: the CSV-parsing traits
  (`Import\Importers\Concerns\ReadsCsvSource` and `ParsesSixBankMaster`) called `fgetcsv()` without the
  `$escape` argument, which raises an `E_DEPRECATED` on PHP 8.4+ ("the $escape parameter must be
  provided"). In a debug-enabled CodeIgniter app that deprecation makes the framework `var_export()`
  the entire raw CSV while rendering the backtrace, which exhausts memory on a real multi-MB source
  file (reported via `spark iban:update --all`). All `fgetcsv()` calls now pass an explicit enclosure
  and an empty escape (`fgetcsv($stream, 0, $delimiter, '"', '')`), opting into the future
  no-legacy-backslash-escape behavior (none of the bundled sources use it). Added a regression test
  that fails if either trait emits the `fgetcsv()` deprecation.

[1.2.1]: https://github.com/daycry/iban-calculator/compare/1.2.0...1.2.1

## [1.2.0] - 2026-07-11

### Added

- **25 more bundled official-source importers** (`src/Import/Importers/`), taking the bundled total
  from 5 to **30** — none of them bundle any actual data, same discipline as v1.1's five (see
  `docs/licensing.md`); the operator runs `iban:update --source=... [--file=...]` themselves:
  - **20 national importers**, one country each: `CzechNationalBankImporter` (CZ, `cnb`),
    `HellenicBankAssociationImporter` (GR, `hba`), `BankOfSloveniaImporter` (SI, `bsi`),
    `NationalBankOfSlovakiaImporter` (SK, `nbs`) — all `;`-delimited CSV; `BulgarianNationalBankImporter`
    (BG, `bnb`, SpreadsheetML XML), `NationalBankOfMoldovaImporter` (MD, `bnm`, XML),
    `NationalBankOfPolandImporter` (PL, `nbp`, XML), `CentralBankOfAzerbaijanImporter` (AZ, `cbar`,
    XML); `NationalBankOfBelgiumImporter` (BE, `nbb`), `CroatianNationalBankImporter` (HR, `hnb`),
    `LuxembourgBankersAssociationImporter` (LU, `abbl`), `CentralBankOfMaltaImporter` (MT, `cbm`),
    `MagyarNemzetiBankImporter` (HU, `mnb`), `BitsNorwayImporter` (NO, `bits`),
    `NationalBankOfGeorgiaImporter` (GE, `nbg`) — all `.xlsx`, read via the new `XlsxReader`;
    `BankOfIsraelImporter` (IL, `boi`), `NationalBankOfUkraineImporter` (UA, `nbu`),
    `NationalBankOfKazakhstanImporter` (KZ, `nbk`) — all JSON; `BrazilianCentralBankImporter` (BR,
    `bcb`, CSV, ODbL) and `LiechtensteinImporter` (LI, `six` — shares `SixImporter`'s CSV source and
    parsing trait, filtered to `Country === 'LI'`).
  - **1 supranational importer, registered 5 times**: `EpcRegisterImporter`, parameterized per
    country and instantiated once each for GB, GI, IE, LV and RO — the SEPA countries whose IBAN
    `bank_code` is exactly the BIC's 4-letter institution prefix and that had no dedicated national
    importer. Consumes the European Payments Council's SEPA Register (per-scheme CSV exports for
    SCT/SCT Inst/SDD Core/SDD B2B); offline mode parses one scheme file, live mode fetches all four
    and derives each country's SEPA reachability per bank.
- **`Import\Support\XlsxReader`**: a minimal, read-only `.xlsx` (OOXML) reader built on `ZipArchive` +
  `SimpleXMLElement` — deliberately not a full spreadsheet library (no PhpSpreadsheet dependency).
  Reads only the first worksheet as a plain grid of cell strings, resolving shared-string/inline-string/
  numeric cells and the real first-sheet part via `workbook.xml`'s relationships. Used by the 7
  `.xlsx`-sourced importers above (BE, HR, LU, MT, HU, NO, GE).
- **EPC SEPA Register importer also populates SEPA reachability flags**: a live (non-offline) run sets
  `sepa_sct`/`sepa_sct_inst`/`sepa_sdd_core`/`sepa_sdd_b2b` per bank from each scheme's own export file
  (`true`/`false`), or leaves a flag `null` if that scheme's file couldn't be fetched at all.
- **`iban:update --all`**: a new flag on `Commands\UpdateCommand` that runs every registered importer
  in a single invocation (all 30, or narrowed with `--country`). Fetches live, honors `--dry-run`,
  isolates each importer in its own `try`/`catch` so one failure doesn't abort the rest, and prints an
  aggregate summary. Cannot be combined with `--file`.
- **Complete public-API documentation**: a new `docs/api-reference.md` — the exhaustive per-symbol
  reference (facade, helper functions, config/services/caching, DTOs, enums, exceptions, national
  validators, contracts/extension points, registry), verified against the source — plus a full
  spark-command reference and the 30-source cheat sheet in `docs/usage.md` and the country coverage
  matrix in `docs/importers.md`.

### Changed

- **Resolver now does a bank-level fallback**: `Resolver::resolve()` tries `findByIban()` first (exact
  bank + branch match); if that misses, it now also tries `findByBankCode($countryCode, $bankIdentifier,
  null)` — a branch-less lookup — before giving up. This lets an IBAN that carries a branch segment
  (e.g. ES/GR/HU/MT/PL/…) resolve to its bank whenever only a bank-level row (`branch_code IS NULL`) was
  seeded, which is what nearly every bundled importer (including the v1.1 `BancoDeEspanaImporter`)
  publishes. Previously such IBANs resolved to nothing unless the exact branch was also seeded.
- **New runtime dependencies**: `ext-iconv` (Windows-125x/Windows-1250/1253 → UTF-8 fallback decoding
  for several national CSV sources) and `ext-zip` (`XlsxReader`'s `ZipArchive` use), both added to
  `composer.json`'s `require` alongside the existing `ext-mbstring`.
- **Internal refactor (behavior-preserving)**: extracted the duplicated importer plumbing into shared
  traits — `Import\Importers\Concerns\NormalizesStrings` (`nullableTrim`/`stripBom`) and `ReadsCsvSource`
  (the `fetch → decode → php://temp → fgetcsv` scaffold, with a per-codepage `decodeCsvBytes()` hook) —
  removing ~30 copy-pasted helper methods across the importers, joining the existing
  `ParsesSixBankMaster`/`ReadsXlsxSource` traits.
- **Quality gates**: 1,041 tests / 2,719 assertions (PHPUnit), PHPStan level 8 clean, PSR-12 clean.

### Fixed

- **`SixImporter` (CH) now filters to `Country === 'CH'`** in the shared SIX Bank Master V3 source —
  previously it imported every row in that file, including Liechtenstein's, mislabeling LI banks as
  Swiss. Liechtenstein now has its own `LiechtensteinImporter` (`Country === 'LI'`) sharing the same
  source file and parsing trait (`Import\Importers\Concerns\ParsesSixBankMaster`).

[1.2.0]: https://github.com/daycry/iban-calculator/compare/1.1.0...1.2.0

## [1.1.0] - 2026-07-11

### Added

- **8 more national check-digit validators**, taking `Core\Validator`'s default `$nationalValidators`
  map from ES-only to **ES/BE/PT/SI/FI/FR/MC/IT/SM** (`src/National/`), each invoked the same way via
  `validate(..., checkNational: true)` / `Config\Iban::$checkNationalByDefault`:
  - `BelgianNationalCheckValidator` (BE) — mod-97 over the first 10 BBAN digits (bank+account), 0
    mapped to 97.
  - `PortugueseNationalCheckValidator` (PT) — weighted mod-97 NIB check over the 19 bank+branch+account
    digits.
  - `SlovenianNationalCheckValidator` (SI) — ISO 7064 MOD 97-10 style check over the 13 bank+account
    digits.
  - `FinnishNationalCheckValidator` (FI) — Luhn (mod-10) check digit over the 13 bank+account digits.
  - `FrenchNationalCheckValidator` (FR, also **MC**) — RIB key (`97 - ((89*bank + 15*branch +
    3*account) mod 97)`, with a letter-to-digit table for alphanumeric account characters).
  - `ItalianNationalCheckValidator` (IT, also **SM**) — CIN odd/even weighted-sum-mod-26 check letter
    over the 22-character ABI+CAB+account tail.
  - Estonia (**EE**) was deliberately **not** added: its real national check depends on a
    bank-specific, variable-length raw domestic account number that can't be reconstructed from the
    fixed-width IBAN fields alone — shipping a generic implementation would reject real, valid IBANs
    (see `src/Core/Validator.php`'s `$nationalValidators` docblock).
- **Optional resolver cache**: `Providers\CachedProvider`, a `ProviderInterface` decorator that caches
  `findByBankCode()`/`findByIban()` lookups (hits **and** misses, via an internal sentinel) behind a
  CI4 `CacheInterface`. Opt-in via `Config\Iban::$cacheTtl` (seconds; default `0` = disabled).
  `Config\Services::iban()` wraps the resolved provider in `CachedProvider` only when `$cacheTtl > 0`
  and the provider isn't `NullProvider` (which never resolves anything, so caching it would be a
  pointless round-trip).
- **Bank-data importer framework**: `Contracts\ImporterInterface` (framework-free contract —
  `countryCode()`, `sourceId()`, `sourceName()`, `license()`, `sourceUrl()`,
  `rows(?string $localFile): iterable`), `Import\ImportReport` (framework-free result DTO),
  `Import\ImporterRegistry` (in-memory catalog keyed by `(countryCode, sourceId)`, registers the 5
  bundled importers via `registerDefaults()`), and `Import\ImportRunner` (CI4-dependent: runs one
  importer against the `banks` table, upserting by the natural key
  `(country_code, bank_code, branch_code)` and stamping provenance —
  `source_id`/`source_version`/`source_license`/`updated_at` — once per run).
- **`iban:update` is now functional**, no longer a no-op: with no `--country`/`--source`, it lists
  the registered importers (`Commands\UpdateCommand`); given a selection, it runs each matching
  importer through `ImportRunner`, honoring `--dry-run` (counts without writing) and `--file=<path>`
  (offline import from a previously-downloaded file instead of a live fetch). Prints an
  `ImportReport` (`fetched`/`imported`/`skipped`) plus the source's name/URL/license per run. v1.0's
  licensing notices (SWIFT IBAN Registry / SWIFT BIC Directory / per-source attribution) are still
  printed first, unconditionally.
- **5 bundled official-source importers** (`src/Import/Importers/`), none of them bundling any actual
  data — the operator runs `iban:update --source=... [--file=...]` themselves, preserving v1.0's
  licensing discipline (see `docs/licensing.md`):
  - `OenbImporter` (AT, `--source=oenb`) — OeNB's semicolon-CSV "Bankstellenverzeichnis", CC-BY-4.0.
  - `BundesbankImporter` (DE, `--source=bundesbank`) — Deutsche Bundesbank's fixed-width
    ISO-8859-1 "Bankleitzahlendatei", free use with mandatory attribution.
  - `SixImporter` (CH, `--source=six`) — SIX Interbank Clearing's semicolon-CSV Bank Master V3
    (UTF-8), "free to use" (the BIC column remains SWIFT's property — not for standalone
    redistribution).
  - `BetaalverenigingImporter` (NL, `--source=betaalvereniging`) — Betaalvereniging Nederland's
    BIC-lijst NL, consumed as a CSV export of the source `.xlsx` (no spreadsheet-parsing dependency);
    reproduction/redistribution requires the publisher's written consent, so this source is
    import-only via `--file`, never live-fetched in practice.
  - `BancoDeEspanaImporter` (ES, `--source=bde`) — Banco de España's MFI-list CSV (the literal
    "Registro de Entidades" is portal/PDF-only), UTF-8 with a leading BOM, reproduction authorized
    subject to attribution and no alteration.
  - New reference doc: `docs/importers.md`.
- **`docs/importers.md`** (new): full reference for the importer subsystem — `ImporterInterface`
  contract, `iban:update` flags with example invocations per source, a table of the 5 bundled
  importers, how provenance is stored, and how to write/register a custom importer.

### Changed

- **`Config\Iban::$defaultFormat` and `$checkNationalByDefault` are now honored**, not just declared:
  the `iban_format()` helper falls back to `$defaultFormat` when its caller omits `$format`, and
  `iban_validate()`/`iban_is_valid()`/`iban_valid()` plus the `iban:validate` command fall back to
  `$checkNationalByDefault` when `--national`/`$checkNational` isn't explicitly passed.
- **New runtime dependency**: `ext-mbstring` (`composer.json`), used by the CSV/fixed-width importers
  to normalize source encodings (`mb_convert_encoding()`/`mb_check_encoding()`). The core validation/
  parsing/formatting path still has zero framework dependencies; the package as a whole now requires
  PHP `^8.3` **and** `ext-mbstring`.
- CI hardening: GitHub Actions pinned by commit SHA, a `composer audit` step added to CI, atomic
  registry-file regeneration in `bin/generate-registry.php`.
- Long-tail country structure corrections in `src/Registry/data/countries.php`, found during the
  national-validator verification work.
- **Quality gates**: 860 tests / 1720 assertions (PHPUnit), PHPStan level 8 clean, PSR-12 clean.

### Fixed

- `Core\Validator` fails fast on over-long input (`MAX_INPUT_LENGTH`) before normalization, a
  defense-in-depth hardening carried over from the pre-v1.1 security audit.

[1.1.0]: https://github.com/daycry/iban-calculator/compare/1.0.0...1.1.0

## [1.0.0] - 2026-07-10

### Added

- **Core validation**: `Daycry\Iban\Core\Validator` implements ISO 13616 structural validation plus a
  MOD-97 (ISO 7064) checksum, over a fixed 8-check pipeline
  (`Blank` → `TooShort` → `IllegalCharacters` → `UnknownCountry` → `BadLength` →
  `MalformedStructure` → `ChecksumFailed` → `NationalCheckFailed`), reported via the
  `ViolationCode` backed enum and `ValidationResult`/`Violation` DTOs. Never throws.
- **Structural registry** covering **78 countries** (`src/Registry/data/countries.php`): IBAN length,
  SWIFT BBAN token grammar, and bank/branch/account/national-check field offsets, independently
  authored from public facts and cross-checked against MIT-licensed reference libraries — never
  derived from the SWIFT IBAN Registry file (see `docs/licensing.md`, `docs/registry-authoring.md`).
  `Registry::VERSION` documents the authorship provenance.
- **Parsing**: `Iban::parse()` (throws `InvalidIbanException` on invalid input) and `tryParse()`
  (returns `null` instead), producing a `ParsedIban` value object with country code, IBAN check
  digits, BBAN, bank/branch/account identifiers, structural national check digit, SEPA country scope,
  and the canonical electronic form.
- **Formatting**: `IbanFormat::Electronic` / `Print` / `Anonymized`, via `Iban::format()` and
  `ParsedIban::format()`. `Anonymized` masks everything except the 2-character country code and the
  last 4 characters (see `docs/formatting.md`).
- **Spanish national check-digit validation**: `SpanishNationalCheckValidator` (weighted mod-11 over
  DC1/DC2), invoked via `validate(..., checkNational: true)`.
- **Bank-entity resolution**: `Iban::resolve()` always returns a `BankResult` (`ParsedIban` + 12
  nullable bank fields + `isResolved()`), backed by a pluggable `ProviderInterface`:
  `NullProvider` (default, zero-config, always unresolved) and `DatabaseProvider` (opt-in, CI4
  `banks` table via `Models\BankModel`). The `banks` table ships empty by design (`BanksSeeder` inserts
  no rows) — see `docs/licensing.md` for why no bank data is bundled.
- **CodeIgniter 4 integration**: `Config\Iban` (`$provider`, `$defaultFormat`,
  `$checkNationalByDefault`, `$dbGroup`, `$table`, all `.env`-overridable), `Config\Services::iban()`
  (auto-discovered `service('iban')`), `Config\Registrar`, and the `iban_helper`
  (`iban_validate`, `iban_is_valid`, `iban_parse`, `iban_format`, `iban_resolve`, `bank_name`,
  `bank_bic`, `iban_country`, `iban_valid`).
- **4 spark commands**: `iban:validate`, `iban:parse`, `iban:resolve` (each with `--json` output), and
  `iban:update` (a documented no-op that prints the bank-data licensing constraints; real importers
  are deferred to v1.1).
- **Zero-dependency core**: `Daycry\Iban\Iban` and everything under `Core/`, `Contracts/`, `DTO/`,
  `Enums/`, `Exceptions/`, `Registry/`, `National/`, `Resolver/` never import CodeIgniter 4, enforced
  by an architecture test (`tests/Architecture/CoreIsFrameworkFreeTest.php`). CodeIgniter 4 is a
  `require-dev`-only peer dependency.
- **Documentation**: `README.md`, `docs/usage.md`, `docs/formatting.md`, `docs/i18n.md`,
  `docs/licensing.md`, `docs/registry-authoring.md`, `docs/architecture.md`, `docs/roadmap.md`.
- **Quality gates**: 686 tests / 1237 assertions (PHPUnit), ~99% coverage, PHPStan level 8 clean
  (`src` + `tests`, with the `codeigniter/phpstan-codeigniter` extension), PSR-12 via PHP-CS-Fixer, all
  enforced in CI across a PHP 8.3/8.4 × CodeIgniter 4 `^4.6` matrix.

[1.0.0]: https://github.com/daycry/iban-calculator/releases/tag/1.0.0
