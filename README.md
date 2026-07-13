<!-- Tests & coverage -->
[![Tests](https://github.com/daycry/iban-calculator/actions/workflows/phpunit.yml/badge.svg)](https://github.com/daycry/iban-calculator/actions/workflows/phpunit.yml)
[![Coverage Status](https://coveralls.io/repos/github/daycry/iban-calculator/badge.svg?branch=main)](https://coveralls.io/github/daycry/iban-calculator?branch=main)

<!-- Static analysis & quality gates -->
[![PHPStan](https://github.com/daycry/iban-calculator/actions/workflows/phpstan.yml/badge.svg)](https://github.com/daycry/iban-calculator/actions/workflows/phpstan.yml)
[![Code Style](https://github.com/daycry/iban-calculator/actions/workflows/code-style.yml/badge.svg)](https://github.com/daycry/iban-calculator/actions/workflows/code-style.yml)
[![CodeQL](https://github.com/daycry/iban-calculator/actions/workflows/codeql.yml/badge.svg)](https://github.com/daycry/iban-calculator/actions/workflows/codeql.yml)

<!-- Package -->
[![Latest Stable Version](https://poser.pugx.org/daycry/iban/v/stable)](https://packagist.org/packages/daycry/iban)
[![Total Downloads](https://poser.pugx.org/daycry/iban/downloads)](https://packagist.org/packages/daycry/iban)
[![License](https://poser.pugx.org/daycry/iban/license)](https://packagist.org/packages/daycry/iban)
[![PHP Version Require](https://poser.pugx.org/daycry/iban/require/php)](https://packagist.org/packages/daycry/iban)

<!-- Tooling -->
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg)](phpstan.neon)
[![Code Style](https://img.shields.io/badge/code%20style-PSR--12-blue.svg)](.php-cs-fixer.dist.php)

<!-- Community -->
[![Stars](https://img.shields.io/github/stars/daycry/iban-calculator.svg)](https://github.com/daycry/iban-calculator/stargazers)
[![Forks](https://img.shields.io/github/forks/daycry/iban-calculator.svg)](https://github.com/daycry/iban-calculator/network)
[![Issues](https://img.shields.io/github/issues/daycry/iban-calculator.svg)](https://github.com/daycry/iban-calculator/issues)

# Daycry Iban

IBAN validation, parsing, formatting and bank-entity resolution for CodeIgniter 4 — usable standalone, with a zero-dependency core.

```bash
composer require daycry/iban
```

## Quickstart

### Facade (framework-free, works standalone)

```php
use Daycry\Iban\Iban;
use Daycry\Iban\Enums\IbanFormat;

$iban = new Iban(); // zero-config: Registry() + NullProvider() by default

$iban->isValid('ES91 2100 0418 4502 0005 1332');           // true

$result = $iban->validate('ES9121000418450200051332', checkNational: true);
$result->isValid();                                          // bool
$result->firstViolation();                                   // ?Violation

$parsed = $iban->parse('ES9121000418450200051332');          // throws InvalidIbanException if invalid
$parsed = $iban->tryParse('not an iban');                    // null instead of throwing
$parsed->countryCode;                                         // 'ES'
$parsed->bankIdentifier;                                      // '2100'

$iban->format($parsed, IbanFormat::Print);                    // 'ES91 2100 0418 4502 0005 1332'
$iban->format($parsed, IbanFormat::Anonymized);                // 'ES******************1332'

$bank = $iban->resolve($parsed);
$bank->isResolved();                                           // false — NullProvider never resolves

// BIC / SWIFT (ISO 9362) — no checksum, so "valid" = well-formed + recognised country
$iban->isValidBic('CAIXESBBXXX');                              // true
$iban->parseBic('NWBKGB2L')->institutionCode;                  // 'NWBK'
$iban->validateIbanAndBic('GB29NWBK60161331926819', 'NWBKGB2L')->isValid(); // true (country + bank coherent)
```

### CI4 service

```php
$svc = service('iban'); // Daycry\Iban\Iban, wired per Config\Iban

$svc->isValid('ES9121000418450200051332'); // true
```

### CI4 helper

```php
helper('iban');

iban_is_valid('ES9121000418450200051332');              // true
iban_valid('ES9121000418450200051332');                  // alias of iban_is_valid()
iban_country('ES9121000418450200051332');                // 'ES'
iban_format('ES9121000418450200051332', 'anonymized');   // 'ES******************1332'
bank_name('ES9121000418450200051332');                    // null with the default NullProvider / empty banks table
bank_bic('ES9121000418450200051332');                      // null, same reason
```

### spark commands

```bash
php spark iban:validate "ES91 2100 0418 4502 0005 1332" --national
php spark iban:validate GB29NWBK60161331926819 --bic=NWBKGB2L   # combined IBAN + BIC cross-check
php spark iban:bic NWBKGB2L                     # validate/parse a BIC on its own
php spark iban:parse ES9121000418450200051332 --json
php spark iban:resolve ES9121000418450200051332
php spark iban:update                          # lists the 30 bundled importers
php spark iban:update --source=oenb --dry-run   # preview an import, write nothing
```

Complete per-symbol API reference: [`docs/api-reference.md`](docs/api-reference.md). Task-oriented guide —
the 16 `ViolationCode` cases, the national validators, caching, and the `Config\Iban` options: see
[`docs/usage.md`](docs/usage.md). Importer/`iban:update` reference: see [`docs/importers.md`](docs/importers.md).

## API overview

The public surface is the facade `Daycry\Iban\Iban` (also what `service('iban')` returns). Every method
accepts a raw string; the ones that already have a parsed value object accept that too.

### IBAN

| Method | What it does |
|---|---|
| `normalize(string $iban): string` | Uppercases and strips spaces/separators to the canonical electronic form. Pure string operation — no validation. |
| `validate(string\|ParsedIban $iban, bool $checkNational = false): ValidationResult` | Full validation: country is in the registry, correct length, BBAN token grammar, and MOD-97 check digits — plus, with `checkNational: true`, the country's national check digit (ES/BE/PT/SI/FI/FR/IT…). **Never throws**; returns a `ValidationResult` exposing `isValid()` and the list of `Violation`s. |
| `isValid(string\|ParsedIban $iban): bool` | Boolean shortcut over `validate()`. |
| `parse(string $iban): ParsedIban` | Validates, then decomposes into a `ParsedIban` (country code, check digits, BBAN, bank identifier, branch identifier, account number, national check digit). **Throws `InvalidIbanException`** (which carries the failing `ValidationResult`) when the IBAN is invalid. |
| `tryParse(string $iban): ?ParsedIban` | Same as `parse()` but returns `null` instead of throwing. |
| `format(string\|ParsedIban $iban, IbanFormat $f = IbanFormat::Print): string` | Renders `Electronic` (no spaces), `Print` (groups of 4), or `Anonymized` (country code + last 4 digits, the rest masked). |
| `resolve(string\|ParsedIban $iban): BankResult` | Looks up the owning bank through the configured provider. **Always returns a `BankResult`** (the `ParsedIban` plus nullable bank fields); `isResolved()` is `false` with the default `NullProvider`, or filled in by `DatabaseProvider` / the iban.com fallback. `resolvedBy` tells you which provider answered. |

### BIC / SWIFT

| Method | What it does |
|---|---|
| `normalizeBic(string $bic): string` | Uppercases and strips whitespace. |
| `validateBic(string\|ParsedBic $bic): ValidationResult` | Structural ISO 9362 validation: length 8 or 11, character classes per position, and country code (positions 5-6) present in the bundled ISO 3166-1 registry. **Never throws.** A BIC has no checksum, so "valid" means *well-formed with a recognised country*, never "this BIC exists". |
| `isValidBic(string\|ParsedBic $bic): bool` | Boolean shortcut over `validateBic()`. |
| `parseBic(string $bic): ParsedBic` | Validates, then slices into a `ParsedBic` (institution, country, location, optional branch code). **Throws `InvalidBicException`** when invalid. |
| `tryParseBic(string $bic): ?ParsedBic` | Same as `parseBic()` but returns `null`. |
| `validateIbanAndBic(?string $iban, ?string $bic): ValidationResult` | The "one, the other, or both" entry point: validates whichever value is provided, and when both are valid also cross-checks them — country always, and bank for the 19 countries whose IBAN bank code is the BIC's 4-letter prefix. |
| `resolveBic(string\|ParsedBic $bic): ?BankInfo` | Resolves a bank straight from a BIC (BIC8 match against the provider's `banks` data). Returns `null` (never throws) on a malformed BIC or an unresolved lookup. |

### Sub-service accessors

`validator()`, `parser()`, `resolver()`, `bicValidator()` and `bicParser()` return the underlying
components, for when you want to reuse a single instance or call them directly.

The helper (`helper('iban')`) mirrors these as plain functions (`iban_validate()`, `iban_parse()`,
`iban_resolve()`, `bic_is_valid()`, `iban_bic_validate()`, …) and every helper is degradation-safe — none
throw. See [`docs/api-reference.md`](docs/api-reference.md) for the exhaustive per-symbol reference.

## Features

- **ISO 13616 + MOD-97 validation** over a structural registry covering 78 countries — length, BBAN token grammar, and field offsets, all compiled into PHP (no runtime data files, no network).
- **Structural parsing**: country code, IBAN check digits, BBAN, bank identifier, branch identifier (where applicable), account number, and national check digit — all as a `ParsedIban` value object.
- **Three output formats**: `Electronic` (canonical, no spaces), `Print` (space-grouped every 4 chars), `Anonymized` (country code + last 4 digits visible, rest masked). See [`docs/formatting.md`](docs/formatting.md).
- **Pluggable bank-entity resolver**: `resolve()` always returns a `BankResult`; bank fields stay `null` with the default `NullProvider`, or get filled in by the optional `DatabaseProvider` once you seed the `banks` table — optionally cached via `Providers\CachedProvider` (`Config\Iban::$cacheTtl`). A bank-level fallback (`findByBankCode($cc, $bank, null)`) resolves branch-carrying IBANs even when only a bank-level row was imported.
- **National check-digit validation for 9 countries** (`checkNational: true`): ES, BE, PT, SI, FI, FR (+MC), IT (+SM) — see [`docs/usage.md`](docs/usage.md#national-check-digit-validators) for the algorithm per country (Estonia is deliberately not covered — its real algorithm needs bank-specific data the IBAN doesn't carry).
- **BIC / SWIFT validation (ISO 9362)**: validate, parse (`ParsedBic`), and — given both an IBAN and a BIC — cross-check them for country and (where structurally possible) bank coherence, plus optional BIC-first bank resolution (`resolveBic()`). Country codes are checked against a bundled 249-code ISO 3166-1 registry, so BICs from non-IBAN countries (US, JP, …) validate too. A BIC has no checksum, so "valid" means *well-formed + recognised country*, never "this BIC exists". Works standalone, no database. See [`docs/usage.md`](docs/usage.md#validating-a-bicswift).
- **30 bundled bank-data importers**, none of them bundling any actual data: `iban:update` lists/runs official-source importers for 25 countries (AT, DE, CH, NL, ES, CZ, GR, SI, SK, BG, MD, PL, AZ, BE, HR, LU, MT, HU, NO, GE, IL, UA, KZ, LI, BR) plus the EPC SEPA Register, which covers GB, GI, IE, LV and RO and also reports SEPA reachability (SCT/SCT Inst/SDD Core/SDD B2B) — live or from a local `--file`. 24 of 42 SEPA countries now resolve. See [`docs/importers.md`](docs/importers.md) for the full list and coverage matrix.
- **Zero-dependency core**: `Daycry\Iban\Iban` and everything under `Core/`, `Contracts/`, `DTO/`, `Enums/`, `Exceptions/`, `Registry/`, `National/`, `Resolver/` never import CodeIgniter — usable in a plain `php -r` script, a CLI tool, or any other framework. (The package as a whole additionally requires `ext-mbstring`, `ext-iconv` and `ext-zip`, used by the bundled importers to normalize source encodings and read `.xlsx` sources.)
- **First-class CI4 integration**: `service('iban')`, `helper('iban')`, `Config\Iban`, and 6 spark commands (`iban:validate`, `iban:parse`, `iban:resolve`, `iban:update`, `iban:publish`, `iban:bic`) — auto-discovered, no manual wiring required.

## Standalone usage (outside CodeIgniter 4)

The core has zero framework dependencies (PHP `^8.3` + `ext-mbstring` + `ext-iconv` + `ext-zip` only), so you can use it without CI4 installed at all:

```php
<?php
require 'vendor/autoload.php';

use Daycry\Iban\Iban;

$iban = new Iban();

var_dump($iban->isValid('DE89370400440532013000')); // bool(true)

$parsed = $iban->parse('FR1420041010050500013M02606');
echo $parsed->countryCode; // 'FR'
```

`codeigniter4/framework` is only a `require-dev` (test/dev) dependency — it is never pulled in by a plain `composer require daycry/iban` for standalone use. `Config\Iban`, `Config\Services`, the spark commands, `Models\BankModel`, the optional `DatabaseProvider`/`CachedProvider`, and `Import\ImportRunner` are the only pieces that need CI4; they simply aren't loaded unless CI4 itself is present in the consuming application. The bundled importers themselves (`Import\Importers/*`) and the `ImporterInterface`/`ImportReport`/`ImporterRegistry` framework stay CI4-free, fetching over plain PHP.

## Architecture

The package is split into two layers connected by a **one-way dependency rule**: the framework-free core and resolver never know about CodeIgniter 4.

```
[ CI4 integration ]  Config, Services('iban'), iban_helper, spark commands   (thin adapter)
        |
        v
[ Resolver ]  ResolverInterface -> ProviderInterface (NullProvider | DatabaseProvider)
        |     produces BankResult (composes ParsedIban + nullable bank data)
        v
[ Core ]  structural registry (in code) -> normalize/validate/parse/format
          zero dependencies, usable outside CI4, produces ParsedIban / ValidationResult
```

See [`docs/architecture.md`](docs/architecture.md) and [`CLAUDE.md`](CLAUDE.md) for the full breakdown, including which directories are guarded (framework-free) and which are allowed to depend on CI4.

## Compatibility matrix

| PHP | CodeIgniter 4 | Status |
|---|---|---|
| 8.3 | ^4.6 | ✅ CI-tested |
| 8.4 | ^4.6 | ✅ CI-tested |

CodeIgniter 4 is optional (`require-dev` only) — the core works on plain PHP 8.3/8.4 with no framework at all.

## Documentation

- [`docs/api-reference.md`](docs/api-reference.md) — the complete public-API reference: every facade method, helper function, config property, DTO, enum, exception, contract, and registry member, verified against the source.
- [`docs/usage.md`](docs/usage.md) — full facade/helper/command API, the 16 `ViolationCode` cases, national check-digit validators, BIC/SWIFT validation, `resolve()` with `NullProvider`/`DatabaseProvider`/`CachedProvider`, `Config\Iban` reference.
- [`docs/importers.md`](docs/importers.md) — the bank-data importer framework, `iban:update` reference, the 30 bundled official-source importers with a coverage matrix, and how to write a custom one.
- [`docs/formatting.md`](docs/formatting.md) — `Electronic` / `Print` / `Anonymized` formats, with the exact `Anonymized` mask scheme.
- [`docs/i18n.md`](docs/i18n.md) — why validation messages are English-only in the core, and how to translate them at the CI4 layer.
- [`docs/licensing.md`](docs/licensing.md) — why no SWIFT/SwiftRef/globalcitizen/Wikipedia data is bundled, and how the registry was independently authored.
- [`docs/registry-authoring.md`](docs/registry-authoring.md) — methodology for authoring/cross-checking the structural country registry.
- [`docs/architecture.md`](docs/architecture.md) — the two-layer architecture and the enforced dependency rule.
- [`docs/roadmap.md`](docs/roadmap.md) — what shipped in v1.1, and what's planned for v2.0.
- [`CHANGELOG.md`](CHANGELOG.md) — release history (Keep a Changelog format).

## Development

```bash
composer update        # this package intentionally ships without composer.lock — see below
composer test           # PHPUnit — 1,269 tests
composer analyze         # PHPStan, level 8 (src + tests, with the CI4 PHPStan extension)
composer cs              # PHP-CS-Fixer, PSR-12, dry-run
```

**No `composer.lock`:** as a library (not an application), `composer.lock` is gitignored on purpose. CI always runs `composer update` against the version constraints in `composer.json`, so the test matrix reflects what consumers actually get.

## License

MIT License - see [LICENSE](LICENSE) file for details.
