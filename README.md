# Daycry Iban

<!-- Tests & coverage -->
[![Tests](https://github.com/daycry/iban-calculator/actions/workflows/phpunit.yml/badge.svg)](https://github.com/daycry/iban-calculator/actions/workflows/phpunit.yml)
[![Coverage Status](https://coveralls.io/repos/github/daycry/iban-calculator/badge.svg?branch=master)](https://coveralls.io/github/daycry/iban-calculator?branch=master)

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

IBAN validation, parsing, formatting and bank-entity resolution for CodeIgniter 4 — usable standalone, with a zero-dependency core.

Validates **all 89 registered national IBAN formats** and BIC/SWIFT country codes for **249 ISO
countries and territories, plus Kosovo (`XK`)**. See the [complete country coverage table](docs/country-coverage.md)
for IBAN lengths, optional national checks and default bank-data importers. Local validation does
not establish that an account or BIC exists; [external verification](docs/verification.md) is opt-in.

```bash
composer require daycry/iban
```

## Contents

- [Features](#features)
- [Country coverage](#country-coverage)
- [Quick Start](#quick-start)
- [API overview](#api-overview)
- [Configuration](#configuration)
- [Database setup](#database-setup-optional)
- [Standalone usage](#standalone-usage-outside-codeigniter-4)
- [Architecture](#architecture)
- [Compatibility matrix](#compatibility-matrix)
- [Documentation](#documentation)
- [Development](#development)
- [License](#license)

---

## Features

### IBAN validation and formatting

- **89 national formats**: validate every registered IBAN prefix, with country-specific length and BBAN character classes.
- **MOD-97 check digits**: verify the international checksum for every supported country.
- **Nine optional national checks**: BE, ES, FI, FR, IT, MC, PT, SI and SM domestic checksum algorithms.
- **Document input normalization**: uppercase input and remove ASCII whitespace, non-breaking spaces and the documented Unicode spacing / zero-width characters.
- **Structural parsing**: expose country, check digits, BBAN, bank, branch, account and national-check fields through `ParsedIban`.
- **Three output formats**: electronic, print groups of four, and anonymized output.
- **Brazil's 2026 profile**: accept the updated alphanumeric bank identifier and account-type/holder fields, as well as older numeric examples.

### SWIFT/BIC and verification

- **ISO 9362 format checks**: validate BIC8 and BIC11 character positions and country codes.
- **Worldwide BIC country coverage**: recognize all 249 officially assigned ISO country codes plus Kosovo (`XK`), including countries without IBANs.
- **BIC parsing**: expose institution, country, location, branch and primary-office information through `ParsedBic`.
- **IBAN/BIC coherence**: compare country codes and bank identifiers for the 22 formats where the bank code is a four-letter BIC prefix, with the documented Martinique exception.
- **Optional external verification**: use `verifyIban()` / `verifyBic()` with iban.com or a custom `VerificationProviderInterface`.
- **Explicit outcomes**: distinguish local validity, unsupported IBAN prefixes, external acceptance, external rejection and unavailable verification.
- **Separate directory evidence**: report BIC existence and network connectivity independently when supplied by the verifier.

### Bank data and integration

- **Pluggable bank resolution**: use the default `NullProvider`, a local `DatabaseProvider` or your own provider; resolve by IBAN or BIC with BIC-aware providers.
- **44 default importer registrations**: populate bank data for 44 IBAN prefixes, including 38 of the 42 SEPA-scheme countries in the importer matrix.
- **Operator-supplied reference CSV**: enrich bank data from an annotated local file while retaining row attribution, leading zeros and existing unrelated attributes.
- **Bank-level fallback**: resolve branch-carrying IBANs against bank-level rows when an exact branch match is unavailable.
- **Optional cache and remote fallback**: cache successful bank lookups and enable iban.com as a last-resort resolver when configured.
- **Framework-free core**: validate, parse and format without CodeIgniter, a database or network access.
- **CodeIgniter 4 integration**: shared services, 16 helper functions, publishable configuration, migrations, seed hooks and six spark commands.
- **Structured results**: immutable DTOs, violation codes and provider provenance for application-level error handling.

## Country coverage

| Capability | Bundled coverage | What it establishes |
|---|---|---|
| IBAN validation | 89 registered national prefixes | Length, BBAN structure and MOD-97 integrity |
| SWIFT/BIC validation | 249 ISO country codes + `XK` | BIC format and recognized country |
| Optional national checks | 9 countries | Additional domestic checksum integrity |
| Default bank-data importers | 44 country registrations | An import path; data must be loaded and can be incomplete |
| External verification | Configured provider | Provider verdict and optional directory evidence |

See the **[complete country-by-country table](docs/country-coverage.md)** for every IBAN prefix,
length, national validator and default importer. The [October 2026 format review](docs/registry-refresh-2026-10.md)
records the source comparison and limitations. Local validity does not confirm account existence,
ownership, bank-directory presence or payment reachability.

---

## Quick Start

### Requirements

- PHP **8.3 or higher**, within the Composer `^8.3` constraint.
- PHP extensions **DOM**, **iconv**, **mbstring** and **zip**.
- Composer.
- **CodeIgniter 4 `^4.6`** when using services, helpers, database providers or spark commands. The core works without CodeIgniter.

### Installation

```bash
composer require daycry/iban
```

Validation works immediately. Database setup and external providers are optional; follow the
[database setup](#database-setup-optional) only when you need bank-data lookups.

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
$invalid = $iban->tryParse('not an iban');                    // null instead of throwing
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

- `iban:validate`: validate an IBAN, optionally with national checks and a BIC cross-check.
- `iban:bic`: validate and parse a BIC independently.
- `iban:parse`: inspect an IBAN's structural fields.
- `iban:resolve`: look up the bank through configured providers.
- `iban:update`: list or run bank-data importers, including dry-run and offline-file modes.
- `iban:publish`: publish the package configuration into the application.

```bash
php spark iban:validate "ES91 2100 0418 4502 0005 1332" --national
php spark iban:validate GB29NWBK60161331926819 --bic=NWBKGB2L   # combined IBAN + BIC cross-check
php spark iban:bic NWBKGB2L                     # validate/parse a BIC on its own
php spark iban:parse ES9121000418450200051332 --json
php spark iban:resolve ES9121000418450200051332
php spark iban:update                          # lists the 44 default importer registrations
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
| `normalize(string $iban): string` | Uppercases, removes ASCII/document whitespace and a leading `IBAN` label. Other invalid characters remain for validation. |
| `validate(string\|ParsedIban $iban, bool $checkNational = false): ValidationResult` | Full validation: country is in the registry, correct length, BBAN token grammar, and MOD-97 check digits — plus, with `checkNational: true`, the country's national check digit (ES/BE/PT/SI/FI/FR/IT…). **Never throws**; returns a `ValidationResult` exposing `isValid()` and the list of `Violation`s. |
| `isValid(string\|ParsedIban $iban): bool` | Boolean shortcut over `validate()`. |
| `parse(string $iban): ParsedIban` | Validates, then decomposes into a `ParsedIban` (country code, check digits, BBAN, bank identifier, branch identifier, account number, national check digit). **Throws `InvalidIbanException`** (which carries the failing `ValidationResult`) when the IBAN is invalid. |
| `tryParse(string $iban): ?ParsedIban` | Same as `parse()` but returns `null` instead of throwing. |
| `format(string\|ParsedIban $iban, IbanFormat $f = IbanFormat::Print): string` | Renders `Electronic` (no spaces), `Print` (groups of 4), or `Anonymized` (country code + last 4 digits, the rest masked). |
| `resolve(string\|ParsedIban $iban): BankResult` | Looks up the owning bank through the configured provider. **Always returns a `BankResult`** (the `ParsedIban` plus nullable bank fields); `isResolved()` is `false` with the default `NullProvider`, or filled in by `DatabaseProvider` / the iban.com fallback. `resolvedBy` tells you which provider answered. |

### BIC / SWIFT

| Method | What it does |
|---|---|
| `normalizeBic(string $bic): string` | Uppercases and removes the same ASCII/document whitespace as IBAN normalization. |
| `validateBic(string\|ParsedBic $bic): ValidationResult` | Structural ISO 9362 validation: length 8 or 11, character classes per position, and country code (positions 5-6) present in the bundled ISO 3166-1 registry. **Never throws.** A BIC has no checksum, so "valid" means *well-formed with a recognised country*, never "this BIC exists". |
| `isValidBic(string\|ParsedBic $bic): bool` | Boolean shortcut over `validateBic()`. |
| `parseBic(string $bic): ParsedBic` | Validates, then slices into a `ParsedBic` (institution, country, location, optional branch code). **Throws `InvalidBicException`** when invalid. |
| `tryParseBic(string $bic): ?ParsedBic` | Same as `parseBic()` but returns `null`. |
| `validateIbanAndBic(?string $iban, ?string $bic): ValidationResult` | Validates either or both identifiers, then checks country coherence (with the documented Martinique exception) and bank coherence for the 22 countries with a four-letter bank identifier. |
| `resolveBic(string\|ParsedBic $bic): ?BankInfo` | Resolves a bank straight from a BIC (BIC8 match against the provider's `banks` data). Returns `null` (never throws) on a malformed BIC or an unresolved lookup. |

### Optional external verification

`verifyIban($iban, checkNational: false)` and `verifyBic($bic)` return a `VerificationResult` that
keeps local validity separate from an optional external verdict. Configure `iban.ibanComApiKey` in
the consuming application, or inject a `VerificationProviderInterface` as the facade's fourth
constructor argument. Ordinary validation stays offline. A BIC's directory presence and network
connectivity are separate flags; IBAN validation does not establish customer-account existence.
See [verification outcomes and examples](docs/verification.md).


```php
$result = service('iban')->verifyBic('CHASUS33');
$result->status->value;     // 'format_valid' without an external verifier
$result->validation->isValid(); // true: local format and country checks passed
$result->external?->exists; // null unless the verifier supplies directory evidence
$result->external?->active; // null unless the verifier supplies network status
```

- `format_valid`: local checks passed, no external verifier selected.
- `format_invalid`: local checks failed; no external request is made.
- `unsupported_country`: the IBAN prefix has no local registered format; no external request is made.
- `externally_validated`: local checks and the external verdict passed.
- `external_rejected`: local checks passed but the provider explicitly rejected the identifier.
- `verification_unavailable`: local checks passed but the provider could not establish a verdict.

### Normalizing pasted identifiers

Both IBAN and BIC input accept ASCII whitespace and common spacing characters copied from PDF or
Office documents: `U+00A0`, `U+2000`–`U+200A`, `U+202F`, `U+3000`, `U+200B` and `U+FEFF`.
Normalization uppercases the identifier; IBAN normalization also removes a leading `IBAN` label.

```php
$iban->normalize("IBAN: es91\u{00A0}2100 0418 4502 0005 1332"); // 'ES9121000418450200051332'
$iban->normalizeBic("caix\u{200B}esbbxxx");                    // 'CAIXESBBXXX'
```

Normalization does not validate the result or remove arbitrary punctuation. See
[normalization and validation](docs/usage.md#normalization-and-validation).

### National checks and IBAN/BIC coherence

- Request domestic checksum checks with `$iban->validate($input, checkNational: true)`.
- Bundled national validators cover **BE, ES, FI, FR, IT, MC, PT, SI and SM**. Countries without one still receive the usual structural and MOD-97 checks.
- `validateIbanAndBic($iban, $bic)` accepts either identifier independently, then cross-checks them when both are present and locally valid.
- Country codes must agree, except for the documented **FR bank `13088` / BIC `BNPAMQMX` or `BNPAMQMXXXX`** pair in Martinique.
- Bank codes are compared only where the IBAN bank identifier is structurally the BIC's four-letter institution prefix; numeric bank codes require directory data for that comparison.
- A coherent IBAN/BIC pair does not establish account existence or bank-directory presence. See the [cross-check reference](docs/api-reference.md#ibanbiccrosschecker).

### Sub-service accessors

`validator()`, `parser()`, `resolver()`, `bicValidator()` and `bicParser()` return the underlying
components, for when you want to reuse a single instance or call them directly.

The helper (`helper('iban')`) mirrors these as plain functions (`iban_validate()`, `iban_parse()`,
`iban_resolve()`, `bic_is_valid()`, `iban_bic_validate()`, …) and every helper is degradation-safe — none
throw. See [`docs/api-reference.md`](docs/api-reference.md) for the exhaustive per-symbol reference.

## Configuration

Publish the CI4 configuration when you need application-specific overrides:

```bash
php spark iban:publish
```

Edit `app/Config/Iban.php`, or set supported `.env` overrides using the `iban.` prefix.

| Property | Default | Purpose |
|---|---|---|
| `$provider` | `'null'` | Bank resolver: `'null'`, `'database'` or a custom `ProviderInterface` class |
| `$defaultFormat` | `'print'` | Default helper format: `'electronic'`, `'print'` or `'anonymized'` |
| `$checkNationalByDefault` | `false` | Default national-check flag for CI4 validation helpers and `iban:validate`; facade callers pass it explicitly |
| `$dbGroup` | `null` | Use CI4's environment-aware default database group, or choose a group explicitly |
| `$table` | `'banks'` | Bank lookup table |
| `$cacheTtl` | `null` | Resolution cache: `null` disables, `0` never expires, a positive integer sets seconds |
| `$ibanComApiKey` | `''` | Opt-in iban.com resolution fallback and explicit external verification |
| `$ibanComTimeout` | `5` | External request timeout in seconds |
| `$isoCountrySource` | `'php'` | BIC country source: bundled PHP list or `'database'` |
| `$isoCountryTable` | `'iso_countries'` | Table used by the optional database ISO loader |

```ini
# .env in the consuming CodeIgniter application
iban.provider = database
iban.cacheTtl = 3600
iban.checkNationalByDefault = true

# Optional: set your own key to enable remote resolution and explicit verification
# iban.ibanComApiKey = your-api-key
# iban.ibanComTimeout = 5
```

- Without an API key, validation and the default provider require no remote service.
- A configured key enables remote bank-resolution fallback; ordinary `validate*()` calls remain local.
- Only explicit `verify*()` calls perform external verification; provider failures return `verification_unavailable`.
- Cache settings apply to bank resolution, not external verification. Only successful bank resolutions are cached.
- Standalone callers pass dependencies through the facade constructor. See the [configuration reference](docs/usage.md#configiban).

## Database setup (optional)

Validation, parsing, formatting and BIC checks need **no database** — the core ships a compiled registry
and works out of the box. The database is only for **bank-entity resolution** (`resolve()` / `resolveBic()`
returning real bank names and BICs) and, optionally, for serving the ISO 3166-1 country list from a table
instead of the bundled compiled list. These steps only apply under CodeIgniter 4.

### 1. Run the migrations

Both tables (`banks` and `iso_countries`) live in the package's module namespace, so migrate it:

```bash
php spark migrate -n "Daycry\Iban"    # creates the `banks` and `iso_countries` tables
# …or run every namespace's migrations together with your app's own:
php spark migrate --all
```

### 2. Enable the database provider

Publish the config (`php spark iban:publish`) and, in `app/Config/Iban.php`, switch the provider:

```php
public string $provider = 'database';   // resolve() now reads the `banks` table
```

### 3. Populate the `banks` table

The `banks` table starts empty. Importers fetch or read operator-supplied sources, with a narrow
curated factual-data exception for AD/VA/SM (see [`docs/licensing.md`](docs/licensing.md)).
Populate the table with the bundled importers:

```bash
php spark iban:update                        # list the 44 default importer registrations and sources
php spark iban:update --source=bundesbank     # import one source (Germany)
php spark iban:update --source=epc            # EPC SEPA Register → GB, GI, IE, LV, RO (+ SEPA flags)
php spark iban:update --all                   # run every importer
php spark iban:update --source=oenb --dry-run  # preview an import, write nothing
php spark iban:update --source=reference-csv --file=/path/to/bank-reference.csv --dry-run
```

The `BanksSeeder` is intentionally empty — it exists only as a seed hook; the importers are the real data
path. See [`docs/importers.md`](docs/importers.md) for the full source list and coverage matrix.
The opt-in [bank reference CSV importer](docs/importers.md#local-bank-reference-csv) accepts additional
licensed data supplied by the consuming application; it is not part of `--all`.

### 4. (Optional) Serve ISO 3166-1 from the database

BIC validation uses the bundled compiled ISO 3166-1 list by default (`$isoCountrySource = 'php'` — nothing
to install). To serve it from the `iso_countries` table instead (so you can edit it in the DB), set
`$isoCountrySource = 'database'` in `app/Config/Iban.php` and seed the table from the compiled list:

```bash
php spark db:seed "Daycry\Iban\Database\Seeds\IsoCountriesSeeder"   # idempotent — upserts by alpha-2
```

## Standalone usage (outside CodeIgniter 4)

The core has zero framework dependencies (PHP `^8.3` + `ext-dom` + `ext-mbstring` + `ext-iconv` + `ext-zip`), so you can use it without CI4 installed at all:

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
| 8.5 | ^4.6 | ✅ CI-tested |

CodeIgniter 4 is optional (`require-dev` only) — the core works on plain PHP 8.3/8.4/8.5 with no framework at all.

## Documentation

- [`docs/api-reference.md`](docs/api-reference.md) — the complete public-API reference: every facade method, helper function, config property, DTO, enum, exception, contract, and registry member, verified against the source.
- [`docs/usage.md`](docs/usage.md) — full facade/helper/command API, the 16 `ViolationCode` cases, national check-digit validators, BIC/SWIFT validation, `resolve()` with `NullProvider`/`DatabaseProvider`/`CachedProvider`, `Config\Iban` reference.
- [`docs/country-coverage.md`](docs/country-coverage.md) — all 89 IBAN prefixes and lengths, BIC format coverage, nine national checksum validators and default importer availability per country.
- [`docs/verification.md`](docs/verification.md) — local validity versus external verification, provider setup and explicit verification outcomes.
- [`docs/registry-refresh-2026-10.md`](docs/registry-refresh-2026-10.md) — October 2026 format review, added prefixes, Brazilian format update and source limitations.
- [`docs/importers.md`](docs/importers.md) — the bank-data importer framework, `iban:update` reference, the 44 default registrations, the opt-in reference CSV importer, and how to write a custom one.
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
composer test           # PHPUnit
composer analyze         # PHPStan, level 8 (src + tests, with the CI4 PHPStan extension)
composer cs              # PHP-CS-Fixer, PSR-12, dry-run
```

**No `composer.lock`:** as a library (not an application), `composer.lock` is gitignored on purpose. CI always runs `composer update` against the version constraints in `composer.json`, so the test matrix reflects what consumers actually get.

## License

MIT License - see [LICENSE](LICENSE) file for details.
