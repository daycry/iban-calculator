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

## Status: v1.0 — feature-complete

| | |
|---|---|
| Version | v1.0.0 |
| Test suite | 686 tests / 1237 assertions, green |
| Coverage | ~99% (project-wide) |
| Static analysis | PHPStan level 8, clean (`src` + `tests`, with the CodeIgniter PHPStan extension) |
| Code style | PSR-12 (PHP-CS-Fixer), clean |
| Country coverage | 78 countries (structural registry) |
| Design spec | [`docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md`](docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md) |
| Changelog | [`CHANGELOG.md`](CHANGELOG.md) |
| Roadmap | [`docs/roadmap.md`](docs/roadmap.md) |

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
php spark iban:parse ES9121000418450200051332 --json
php spark iban:resolve ES9121000418450200051332
php spark iban:update
```

Full API reference, the 8 `ViolationCode` cases, and the `Config\Iban` options: see [`docs/usage.md`](docs/usage.md).

## Features

- **ISO 13616 + MOD-97 validation** over a structural registry covering 78 countries — length, BBAN token grammar, and field offsets, all compiled into PHP (no runtime data files, no network).
- **Structural parsing**: country code, IBAN check digits, BBAN, bank identifier, branch identifier (where applicable), account number, and national check digit — all as a `ParsedIban` value object.
- **Three output formats**: `Electronic` (canonical, no spaces), `Print` (space-grouped every 4 chars), `Anonymized` (country code + last 4 digits visible, rest masked). See [`docs/formatting.md`](docs/formatting.md).
- **Pluggable bank-entity resolver**: `resolve()` always returns a `BankResult`; bank fields stay `null` with the default `NullProvider`, or get filled in by the optional `DatabaseProvider` once you seed the `banks` table.
- **Spanish national check-digit validation** (mod-11, DC1/DC2) via `checkNational: true`, with the hook designed for more countries to register later.
- **Zero-dependency core**: `Daycry\Iban\Iban` and everything under `Core/`, `Contracts/`, `DTO/`, `Enums/`, `Exceptions/`, `Registry/`, `National/`, `Resolver/` never import CodeIgniter — usable in a plain `php -r` script, a CLI tool, or any other framework.
- **First-class CI4 integration**: `service('iban')`, `helper('iban')`, `Config\Iban`, and 4 spark commands (`iban:validate`, `iban:parse`, `iban:resolve`, `iban:update`) — auto-discovered, no manual wiring required.

## Standalone usage (outside CodeIgniter 4)

The core has zero runtime dependencies, so you can use it without CI4 installed at all:

```php
<?php
require 'vendor/autoload.php';

use Daycry\Iban\Iban;

$iban = new Iban();

var_dump($iban->isValid('DE89370400440532013000')); // bool(true)

$parsed = $iban->parse('FR1420041010050500013M02606');
echo $parsed->countryCode; // 'FR'
```

`codeigniter4/framework` is only a `require-dev` (test/dev) dependency — it is never pulled in by a plain `composer require daycry/iban` for standalone use. `Config\Iban`, `Config\Services`, the spark commands, `Models\BankModel`, and the optional `DatabaseProvider` are the only pieces that need CI4; they simply aren't loaded unless CI4 itself is present in the consuming application.

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

- [`docs/usage.md`](docs/usage.md) — full facade/helper/command API, the 8 `ViolationCode` cases, `resolve()` with `NullProvider` vs `DatabaseProvider`, `Config\Iban` reference.
- [`docs/formatting.md`](docs/formatting.md) — `Electronic` / `Print` / `Anonymized` formats, with the exact `Anonymized` mask scheme.
- [`docs/i18n.md`](docs/i18n.md) — why validation messages are English-only in the core, and how to translate them at the CI4 layer.
- [`docs/licensing.md`](docs/licensing.md) — why no SWIFT/SwiftRef/globalcitizen/Wikipedia data is bundled, and how the registry was independently authored.
- [`docs/registry-authoring.md`](docs/registry-authoring.md) — methodology for authoring/cross-checking the structural country registry.
- [`docs/architecture.md`](docs/architecture.md) — the two-layer architecture and the enforced dependency rule.
- [`docs/roadmap.md`](docs/roadmap.md) — what's planned for v1.1 and v2.0.
- [`CHANGELOG.md`](CHANGELOG.md) — release history (Keep a Changelog format).

## Development

```bash
composer update        # this package intentionally ships without composer.lock — see below
composer test           # PHPUnit — 686 tests
composer analyze         # PHPStan, level 8 (src + tests, with the CI4 PHPStan extension)
composer cs              # PHP-CS-Fixer, PSR-12, dry-run
```

**No `composer.lock`:** as a library (not an application), `composer.lock` is gitignored on purpose. CI always runs `composer update` against the version constraints in `composer.json`, so the test matrix reflects what consumers actually get.

## License

MIT License - see [LICENSE](LICENSE) file for details.
