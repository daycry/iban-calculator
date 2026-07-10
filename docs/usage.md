# Usage

Full API reference for `daycry/iban` v1.0. Every example on this page has been run against the real
code (see `tests/` for the source of truth these examples are drawn from).

- [The facade: `Daycry\Iban\Iban`](#the-facade-daycryibaniban)
- [Validation and the 8 `ViolationCode` cases](#validation-and-the-8-violationcode-cases)
- [Parsing](#parsing)
- [Formatting](#formatting)
- [Resolving bank data: `NullProvider` vs `DatabaseProvider`](#resolving-bank-data-nullprovider-vs-databaseprovider)
- [`Config\Iban`](#configiban)
- [The `iban_helper`](#the-iban_helper)
- [spark commands](#spark-commands)

## The facade: `Daycry\Iban\Iban`

`Daycry\Iban\Iban` is the single entry point for the library. It implements all three core contracts
(`ValidatorInterface`, `ParserInterface`, `ResolverInterface`) by delegating to internally-wired
sub-services, and is default-constructible:

```php
use Daycry\Iban\Iban;
use Daycry\Iban\Registry\Registry;
use Daycry\Iban\Providers\NullProvider;

$iban = new Iban(); // == new Iban(new Registry(), new NullProvider())
```

In a CodeIgniter 4 app, get the shared instance (wired from `Config\Iban`) via `service('iban')`
instead of `new Iban()` — see [`Config\Iban`](#configiban) below.

| Method | Signature | Notes |
|---|---|---|
| `validate()` | `validate(string\|ParsedIban $iban, bool $checkNational = false): ValidationResult` | Never throws. |
| `isValid()` | `isValid(string\|ParsedIban $iban): bool` | Never throws. Shorthand for `validate()->isValid()` (without the national check). |
| `normalize()` | `normalize(string $iban): string` | Trims, uppercases, strips spaces. Does not validate. |
| `parse()` | `parse(string $iban): ParsedIban` | **Throws `InvalidIbanException`** on an invalid IBAN. |
| `tryParse()` | `tryParse(string $iban): ?ParsedIban` | Same as `parse()` but returns `null` instead of throwing. |
| `format()` | `format(string\|ParsedIban $iban, IbanFormat $f = IbanFormat::Print): string` | Does **not** require validity — it normalizes and presents whatever it's given. |
| `resolve()` | `resolve(string\|ParsedIban $iban): BankResult` | Parses first (throws `InvalidIbanException` for a string that fails validation), then overlays bank data via the configured provider. |
| `validator()` | `validator(): Core\Validator` | Sub-service accessor. |
| `parser()` | `parser(): Core\Parser` | Sub-service accessor. |
| `resolver()` | `resolver(): Resolver\Resolver` | Sub-service accessor. |

```php
use Daycry\Iban\Iban;
use Daycry\Iban\Enums\IbanFormat;
use Daycry\Iban\Exceptions\InvalidIbanException;

$iban = new Iban();

$iban->isValid('ES9121000418450200051332');      // true
$iban->isValid('not an iban');                     // false, never throws

$iban->normalize('  es91 2100 0418 4502 0005 1332  '); // 'ES9121000418450200051332'

try {
    $parsed = $iban->parse('basura');
} catch (InvalidIbanException $e) {
    $e->result();           // the ValidationResult that caused the failure
    $e->getMessage();       // the first violation's message
}

$parsed = $iban->tryParse('basura'); // null, no exception

$iban->format('ES9121000418450200051332', IbanFormat::Electronic); // 'ES9121000418450200051332'
$iban->format('ES9121000418450200051332', IbanFormat::Print);      // 'ES91 2100 0418 4502 0005 1332'
$iban->format('ES9121000418450200051332', IbanFormat::Anonymized); // 'ES******************1332'
```

## Validation and the 8 `ViolationCode` cases

`validate()` runs a **fixed, ordered pipeline** and short-circuits on the first violation found — so
an IBAN that fails two checks at once always reports the earlier one:

1. `Blank`
2. `TooShort`
3. `IllegalCharacters`
4. `UnknownCountry`
5. `BadLength`
6. `MalformedStructure`
7. `ChecksumFailed`
8. `NationalCheckFailed` (only when `checkNational: true` **and** a national validator is registered
   for the country — otherwise this step is a silent skip, never a failure)

A valid IBAN returns `new ValidationResult(true, [])`. An invalid one returns
`new ValidationResult(false, [$violation])`, where `$violation` is a `Violation` DTO:
`$code` (`ViolationCode` enum case), `$messageKey` (a stable, translatable string like
`'iban.violation.checksum_failed'`), `$message` (the English default text — see
[`docs/i18n.md`](i18n.md)).

```php
use Daycry\Iban\Enums\ViolationCode;

$result = $iban->validate('ES9021000418450200051332'); // check digits tampered: 91 -> 90

$result->isValid();          // false
$result->violations();       // [Violation{...}]
$violation = $result->firstViolation();
$violation->code;            // ViolationCode::ChecksumFailed
$violation->code->value;     // 'checksum_failed'
$violation->messageKey;      // 'iban.violation.checksum_failed'
$violation->message;         // 'The IBAN check digits are invalid.'
```

| `ViolationCode` | `->value` | Triggered when | Example input |
|---|---|---|---|
| `Blank` | `blank` | The input normalizes to an empty string (empty, or all whitespace). | `''`, `'   '` |
| `TooShort` | `too_short` | Normalized length is under 4 characters (too short to even hold a country code + check digits). | `'ES9'` |
| `IllegalCharacters` | `illegal_characters` | After normalization, a character outside `[A-Z0-9]` remains. | `'ES91-2100'` |
| `UnknownCountry` | `unknown_country` | The first 2 characters aren't a country code present in the registry. | `'ZZ9121000418450200051332'` |
| `BadLength` | `bad_length` | The normalized length doesn't match the registered IBAN length for that country. | `'ES912100041845020005133'` (23 chars, ES needs 24) |
| `MalformedStructure` | `malformed_structure` | The check digits aren't numeric, or the BBAN doesn't match the country's SWIFT token grammar (e.g. a letter where a digit is required). | `'ES9121000418450200051X32'` |
| `ChecksumFailed` | `checksum_failed` | Structurally sound, but the MOD-97 (ISO 7064) checksum over the check digits fails. | `'ES9021000418450200051332'` |
| `NationalCheckFailed` | `national_check_failed` | `checkNational: true` was requested, a national validator is registered for the country (only `ES` in v1.0), and its check fails. | `$iban->validate('ES2921000418460200051332', checkNational: true)` (bank/branch/account of the canonical ES fixture, but national check digits `46` instead of the correct `45`) |

```php
// NationalCheckFailed only fires when explicitly requested:
$iban->isValid('ES2921000418460200051332');                              // true  (MOD-97 passes; national check not run)
$iban->validate('ES2921000418460200051332', checkNational: true)
     ->firstViolation()->code;                                            // ViolationCode::NationalCheckFailed

// Countries without a registered national validator silently skip the check:
$iban->validate('DE89370400440532013000', checkNational: true)->isValid(); // true
```

## Parsing

`parse()`/`tryParse()` validate first, then slice the normalized IBAN into structural fields per the
country's registered offsets, returning a `ParsedIban`:

```php
$parsed = $iban->parse('ES9121000418450200051332');

$parsed->countryCode;         // 'ES'
$parsed->checkDigits;         // '91'
$parsed->bban;                 // '21000418450200051332'
$parsed->bankIdentifier;      // '2100'
$parsed->branchIdentifier;    // '0418' (null for countries with no branch field, e.g. DE/NL/BE)
$parsed->accountNumber;       // '0200051332'
$parsed->nationalCheckDigit;  // '45' (structural extraction only in v1.0 — not the same as checkNational validation)
$parsed->sepaCountry;         // true — EPC409-09 country-level SEPA scope, from the registry
$parsed->electronic;          // 'ES9121000418450200051332' — canonical normalized form
(string) $parsed;              // same as ->electronic (ParsedIban::__toString())

$parsed->format(\Daycry\Iban\Enums\IbanFormat::Print); // 'ES91 2100 0418 4502 0005 1332'
```

## Formatting

See [`docs/formatting.md`](formatting.md) for the full reference, including the exact `Anonymized`
mask scheme.

## Resolving bank data: `NullProvider` vs `DatabaseProvider`

`resolve()` always returns a `BankResult` — a `ParsedIban` plus 12 nullable bank-data fields
(`bankName`, `shortName`, `bic`, `city`, `address`, `sepaSct`, `sepaSctInst`, `sepaSddCore`,
`sepaSddB2b`, `sourceId`, `sourceVersion`, `sourceLicense`) and `isResolved(): bool` (`true` iff any of
those 12 fields is non-null).

### `NullProvider` (default — no database required)

`service('iban')` and `new Iban()` both default to `NullProvider`, which `supports()` nothing and
resolves nothing:

```php
$iban = new Iban(); // NullProvider by default

$bank = $iban->resolve('ES9121000418450200051332');
$bank->isResolved(); // false
$bank->bankName;     // null
$bank->iban;         // the ParsedIban — always populated, regardless of provider
```

This is what makes the library fully usable with **zero setup**: no migration, no seed, no database
connection needed for validation, parsing, or formatting. Only `resolve()`'s bank-data fields degrade.

### `DatabaseProvider` (opt-in, CI4 only)

`DatabaseProvider` looks bank rows up in the `banks` table via `Models\BankModel`. To use it in a CI4
app:

1. **Configure it** — publish `Config\Iban` (or set the `iban.provider` environment variable) to
   `'database'`:

   ```php
   // app/Config/Iban.php
   namespace Config;

   class Iban extends \Daycry\Iban\Config\Iban
   {
       public string $provider = 'database';
   }
   ```

2. **Run the migration** — the package ships `CreateBanksTable`
   (`src/Database/Migrations/2026-07-10-000001_CreateBanksTable.php`), discovered automatically via
   the `Daycry\Iban\` Composer namespace:

   ```bash
   php spark migrate --namespace "Daycry\Iban"
   # or, to run every discovered namespace's pending migrations:
   php spark migrate -all
   ```

   The `banks` table has: `id`, `country_code`, `bank_code`, `branch_code`, `bic`, `name`,
   `short_name`, `city`, `address`, `sepa_sct`, `sepa_sct_inst`, `sepa_sdd_core`, `sepa_sdd_b2b`,
   `source_id`, `source_version`, `source_license`, `updated_at` — with a unique key on
   `(country_code, bank_code, branch_code)`.

3. **Seed it** — the bundled `BanksSeeder` is **intentionally empty** (v1.0 ships zero bank data; see
   [`docs/licensing.md`](licensing.md) for why). It exists only as the canonical seed hook:

   ```bash
   php spark db:seed "Daycry\Iban\Database\Seeds\BanksSeeder"
   ```

   To resolve real bank data, seed the `banks` table yourself (e.g. from your own licensed source) or
   wait for the v1.1 importers described in [`docs/roadmap.md`](roadmap.md).

Once rows exist, `resolve()` finds them by IBAN (`findByIban()`, tried first) or by
country+bank+branch code (`findByBankCode()`, fallback):

```php
$bank = $iban->resolve('ES9121000418450200051332'); // with a matching row seeded

$bank->isResolved(); // true
$bank->bankName;     // e.g. 'CaixaBank'
$bank->bic;          // e.g. 'CAIXESBBXXX'
```

`DatabaseProvider::supports()` always returns `true` (it queries any country and simply returns `null`
when nothing is seeded), so the resolver never skips the lookup outright the way it would for a
provider scoped to specific countries.

## `Config\Iban`

Publishable, `.env`-overridable configuration (`Daycry\Iban\Config\Iban`, `BaseConfig` subclass). Every
property is overridable via `.env` using the `iban.<property>` prefix, e.g. `iban.provider = database`.

| Property | Default | Meaning |
|---|---|---|
| `$provider` | `'null'` | `'null'` (no lookups — the safe default), `'database'` (`DatabaseProvider`), or the fully-qualified class name of a custom `ProviderInterface` implementation. |
| `$defaultFormat` | `'print'` | `'electronic'`, `'print'`, or `'anonymized'` — the default `IbanFormat` when a caller doesn't explicitly request one (currently consulted by consumers of the config; the facade's own `format()` still defaults its `IbanFormat $f` parameter to `IbanFormat::Print`). |
| `$checkNationalByDefault` | `false` | Whether national check-digit validation should run by default. |
| `$dbGroup` | `'default'` | The `Config\Database` connection group queried by `DatabaseProvider` / `BankModel`. |
| `$table` | `'banks'` | The table name queried by `DatabaseProvider` / `BankModel`. |

To override, publish your own `App\Config\Iban extends \Daycry\Iban\Config\Iban` (CI4's `Factories`
resolution picks up the app's version automatically), or set the matching `.env` variables.

## The `iban_helper`

Procedural convenience wrappers around `service('iban')`. Load it with `helper('iban')` (or add
`'iban'` to `Config\Autoload::$helpers` for an always-on helper). Every function is guarded by
`function_exists()`, so re-inclusion never fatals.

| Function | Signature | Behavior |
|---|---|---|
| `iban_validate()` | `(string $iban): ValidationResult` | Delegates to `validate()`. Never throws. |
| `iban_is_valid()` | `(string $iban): bool` | Delegates to `isValid()`. Never throws. |
| `iban_parse()` | `(string $iban): ?ParsedIban` | Always uses `tryParse()` under the hood — returns `null` instead of throwing, unlike the facade's own `parse()`. |
| `iban_format()` | `(string $iban, string $format = 'print'): string` | `$format` is `'electronic'`, `'print'` (default), or `'anonymized'` (case-insensitive); anything else falls back to `'print'`. |
| `iban_resolve()` | `(string $iban): BankResult` | Delegates straight to `resolve()` — **not** degradation-safe: throws `InvalidIbanException` for an invalid IBAN, same as the facade. |
| `bank_name()` | `(string $iban): ?string` | Safe: `null` for an invalid IBAN or an unresolved entity. Never throws. |
| `bank_bic()` | `(string $iban): ?string` | Same safety guarantee as `bank_name()`. |
| `iban_country()` | `(string $iban): ?string` | `null` for an invalid IBAN. Never throws. |
| `iban_valid()` | `(string $iban): bool` | Alias of `iban_is_valid()`. |

```php
helper('iban');

iban_validate('ES9121000418450200051332');       // ValidationResult{valid: true, violations: []}
iban_is_valid('ES9121000418450200051332');         // true
iban_valid('ES9121000418450200051332');             // true (alias)
iban_parse('basura');                                 // null
iban_format('ES9121000418450200051332', 'ELECTRONIC'); // 'ES9121000418450200051332' (case-insensitive)
iban_country('ES9121000418450200051332');            // 'ES'
bank_name('ES9121000418450200051332');                // null (NullProvider / empty banks table)
bank_bic('ES9121000418450200051332');                  // null, same reason
```

## spark commands

All 4 commands are grouped under `IBAN` in `php spark list` and are auto-discovered — no manual
registration needed.

### `iban:validate <iban> [--national] [--json]`

Thin wrapper over `service('iban')->validate()`. Exit code mirrors the result: `0` for a valid IBAN,
`1` otherwise — usable directly in shell scripts/CI.

```bash
$ php spark iban:validate "ES91 2100 0418 4502 0005 1332"
VALID

$ php spark iban:validate ES9021000418450200051332
INVALID: checksum_failed - The IBAN check digits are invalid.

$ php spark iban:validate ES9021000418450200051332 --json
{
    "valid": false,
    "violation": {
        "code": "checksum_failed",
        "message": "The IBAN check digits are invalid."
    }
}

$ php spark iban:validate ES2921000418460200051332 --national --json
```

### `iban:parse <iban> [--json]`

Wraps `tryParse()`. Prints a CLI table of the 9 `ParsedIban` fields by default, or `--json` for
machine-readable output. An invalid IBAN prints `CLI::error('Invalid IBAN')` and exits `1` (no
exception is thrown).

```bash
$ php spark iban:parse ES9121000418450200051332 --json
{
    "countryCode": "ES",
    "checkDigits": "91",
    "bban": "21000418450200051332",
    "bankIdentifier": "2100",
    "branchIdentifier": "0418",
    "accountNumber": "0200051332",
    "nationalCheckDigit": "45",
    "sepaCountry": true,
    "electronic": "ES9121000418450200051332"
}
```

### `iban:resolve <iban> [--json]`

Wraps `resolve()`. Prints all `ParsedIban` fields plus the 12 bank-data fields and `isResolved`. With
the default empty `banks` table, prints a yellow note that only structural fields are available.

```bash
$ php spark iban:resolve ES9121000418450200051332
...
Note: no provider data (empty bank DB) — structural fields only.
```

### `iban:update [--source=<source>] [--country=<country>] [--dry-run]`

**Documented no-op in v1.0.** This package ships zero bank-data importers (see
[`docs/licensing.md`](licensing.md) for why the SWIFT IBAN Registry / SwiftRef BIC Directory can't be
bundled). Running it prints the licensing notices and confirms `Registered importers: 0`. The
`--source`/`--country`/`--dry-run` flags are accepted for forward compatibility with the real importer
command planned for v1.1 (see [`docs/roadmap.md`](roadmap.md)) but change nothing today.

```bash
$ php spark iban:update
SWIFT IBAN Registry is non-commercial/no-derivatives (not bundled).
SWIFT BIC Directory (SwiftRef) is proprietary (not bundled).
National lists require per-source attribution.
Registered importers: 0
No importers bundled — bank-data import is deferred to v1.1. This command is a documented no-op in v1.0.
```
