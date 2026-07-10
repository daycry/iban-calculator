# Changelog

All notable changes to `daycry/iban` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
