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

## Status: v1.0 in development

This package is **greenfield** and under active development toward v1.0. **Phase 1 (Foundations) is complete**: tooling, CI, the domain model (DTOs, enums, exceptions) and all six framework-free contracts are in place and frozen. Everything from the structural country registry onward (Phases 2–8) is still to be built — see the roadmap for the full breakdown.

| | |
|---|---|
| Current phase | ✅ Phase 1 — Foundations (12/12 tasks) |
| Test suite | 71 tests / 144 assertions, green |
| Static analysis | PHPStan level 8, clean |
| Code style | PSR-12 (PHP-CS-Fixer), clean |
| Design spec | [`docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md`](docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md) |
| Roadmap | [`docs/roadmap/2026-07-10-daycry-iban-v1/`](docs/roadmap/2026-07-10-daycry-iban-v1/) (spec, evaluation, plan, task checklist) |
| Architecture overview | [`docs/architecture.md`](docs/architecture.md) |

Nothing below the "What's implemented today" section is usable yet — the "Planned API" block shows the intended v1.0 surface, quoted from the spec, so integrators can see where this is headed.

## Architecture

The package is split into two layers connected by a **one-way dependency rule**: the core never knows about the resolver or about CodeIgniter 4.

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

- **Framework-free** (`src/Contracts`, `src/DTO`, `src/Enums`, `src/Exceptions`, and — once built — `src/Core`): no `codeigniter4/*` imports anywhere here. Enforced by an architecture test (`tests/Architecture/CoreIsFrameworkFreeTest.php`).
- **CI4-aware** (`src/Config`, `src/Commands`, `src/Models`, `src/Database`, `src/Providers/DatabaseProvider`, `src/Helpers`): the only places allowed to depend on CodeIgniter 4, kept as a thin adapter with no domain logic.

See [`docs/architecture.md`](docs/architecture.md) for details.

## What's implemented / Roadmap

**Done (Phase 1 — Foundations):**
- Tooling & CI: `composer.json` (PHP `^8.3`, CodeIgniter 4 `^4.6` as a dev-only peer), PHPStan level 8, PHP-CS-Fixer (PSR-12), GitHub Actions.
- Enums: `ViolationCode` (8 cases), `IbanFormat` (`Electronic` / `Print` / `Anonymized`).
- Immutable `final readonly` DTOs: `Violation`, `ValidationResult`, `ParsedIban`, `BankInfo`, `BankResult`.
- Exceptions: `IbanException`, `InvalidIbanException` (carries the `ValidationResult` that caused a strict parse failure).
- All six framework-free contracts, now frozen: `ValidatorInterface`, `ParserInterface`, `ProviderInterface`, `ResolverInterface`, `RegistryLoaderInterface`, `NationalCheckValidatorInterface`.

**Not built yet (Phases 2–8 — do not rely on these):**
- Phase 2 — the structural country registry (~84 countries: IBAN length, BBAN token grammar, bank/branch/account/national-check offsets).
- Phase 3 — the algorithmic core: `Normalizer`, `Mod97`, `StructureCompiler`, `Validator`, `Parser`, `Formatter`.
- Phase 4 — the Spanish national check digit validator (mod-11) and the registry data generator (`bin/`).
- Phase 5 — `Resolver`, `NullProvider`, the optional `DatabaseProvider` (Model + Migration + empty Seed) and the `Iban` facade.
- Phase 6 — CodeIgniter 4 integration: `Config\Iban`, `Services::iban()`, the `iban_helper`, spark commands.
- Phase 7 — cross-cutting test fixtures/infrastructure; Phase 8 — full usage documentation.

Full breakdown with acceptance criteria per task: [`docs/roadmap/2026-07-10-daycry-iban-v1/tasks.md`](docs/roadmap/2026-07-10-daycry-iban-v1/tasks.md).

### Planned API (v1.0)

The following is the **intended** public surface once Phases 2–5 land — quoted from the design spec, not runnable today.

```php
use Daycry\Iban\Iban;
use Daycry\Iban\Enums\IbanFormat;

$iban = new Iban($registry); // provider defaults to NullProvider

// Validation — never throws.
$result = $iban->validate('ES9121000418450200051332', checkNational: true);
$result->isValid();
$result->firstViolation(); // ?Violation

// Parsing — throws InvalidIbanException on strict failure, or use tryParse().
$parsed = $iban->parse('ES91 2100 0418 4502 0005 1332');
$parsed->countryCode;      // 'ES'
$parsed->bankIdentifier;
$parsed->format(IbanFormat::Print); // 'ES91 2100 0418 4502 0005 1332'

// Resolution — always returns a BankResult; bank fields are null with NullProvider.
$bank = $iban->resolve($parsed);
$bank->isResolved(); // false until a provider (e.g. DatabaseProvider) has data
```

Contracts backing this API already exist today (`ValidatorInterface`, `ParserInterface`, `ResolverInterface`, etc.) — only the concrete implementations are pending.

## Development

```bash
composer update        # this package intentionally ships without composer.lock — see below
composer test           # PHPUnit
composer analyze         # PHPStan, level 8
composer cs              # PHP-CS-Fixer, PSR-12, dry-run
```

**No `composer.lock`:** as a library (not an application), `composer.lock` is gitignored on purpose. CI always runs `composer update` against the version constraints in `composer.json`, so the test matrix reflects what consumers actually get.

## License

MIT License - see [LICENSE](LICENSE) file for details.
