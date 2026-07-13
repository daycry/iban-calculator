# Usage

Task-oriented guide for `daycry/iban`, current through v2.1. Every example on this page has been run
against the real code (see `tests/` for the source of truth these examples are drawn from). For the
exhaustive per-symbol reference — every facade method, helper function, config property, DTO field,
enum case, exception, and contract — see [`docs/api-reference.md`](api-reference.md).

- [The facade: `Daycry\Iban\Iban`](#the-facade-daycryibaniban)
- [Validation and the 8 `ViolationCode` cases](#validation-and-the-8-violationcode-cases)
- [National check-digit validators](#national-check-digit-validators)
- [Parsing](#parsing)
- [Formatting](#formatting)
- [Validating a BIC/SWIFT](#validating-a-bicswift)
- [Resolving bank data: `NullProvider` vs `DatabaseProvider`](#resolving-bank-data-nullprovider-vs-databaseprovider)
- [Caching resolved lookups: `CachedProvider`](#caching-resolved-lookups-cachedprovider)
- [iban.com fallback: `IbanComProvider` + `ChainProvider`](#ibancom-fallback-ibancomprovider--chainprovider)
- [Knowing whether a result came from your database or the iban.com fallback](#knowing-whether-a-result-came-from-your-database-or-the-ibancom-fallback)
- [`Config\Iban`](#configiban)
- [The `iban_helper`](#the-iban_helper)
- [spark commands](#spark-commands)
- [Bank-data importers (`iban:update`)](#bank-data-importers-ibanupdate)

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
| `NationalCheckFailed` | `national_check_failed` | `checkNational: true` was requested, a national validator is registered for the country (`ES`/`BE`/`PT`/`SI`/`FI`/`FR`/`MC`/`IT`/`SM` as of v1.1 — see [National check-digit validators](#national-check-digit-validators)), and its check fails. | `$iban->validate('ES2921000418460200051332', checkNational: true)` (bank/branch/account of the canonical ES fixture, but national check digits `46` instead of the correct `45`) |

```php
// NationalCheckFailed only fires when explicitly requested:
$iban->isValid('ES2921000418460200051332');                              // true  (MOD-97 passes; national check not run)
$iban->validate('ES2921000418460200051332', checkNational: true)
     ->firstViolation()->code;                                            // ViolationCode::NationalCheckFailed

// Countries without a registered national validator silently skip the check:
$iban->validate('DE89370400440532013000', checkNational: true)->isValid(); // true
```

## National check-digit validators

Beyond the whole-IBAN MOD-97 checksum (always checked), some countries embed their own *national*
check digit(s) inside the BBAN — a second, country-specific arithmetic check. `Core\Validator`'s
`$nationalValidators` map (`src/Core/Validator.php`) wires one `NationalCheckValidatorInterface`
implementation (`src/National/`) per supported country code; `validate(..., checkNational: true)`
consults it after the MOD-97 check passes.

| Country | Class | Algorithm |
|---|---|---|
| `ES` | `SpanishNationalCheckValidator` | Weighted mod-11 over two check digits: DC1 over `00`+bank+branch, DC2 over the account number. |
| `BE` | `BelgianNationalCheckValidator` | Mod-97 of the first 10 BBAN digits (3-digit bank + 7-digit account) as an integer; a `0` remainder maps to `97` (never `00`). |
| `PT` | `PortugueseNationalCheckValidator` | Weighted mod-97 NIB check: `98 - (sum of the 19 bank+branch+account digits × fixed weights, mod 97)`, collapsing `98→0` and `97→1`. |
| `SI` | `SlovenianNationalCheckValidator` | ISO 7064 MOD 97-10 style check over the 13 bank+account digits: `98 - ((13-digit number × 100) mod 97)`. |
| `FI` | `FinnishNationalCheckValidator` | Luhn (mod-10) check digit over the 13 bank+account digits, computed right-to-left with every second digit doubled. |
| `FR`, `MC` | `FrenchNationalCheckValidator` | RIB key: `97 - ((89×bank + 15×branch + 3×account) mod 97)`, with alphanumeric account characters mapped to digits via the RIB letter table first. Monaco shares France's exact BBAN structure and algorithm. |
| `IT`, `SM` | `ItalianNationalCheckValidator` | CIN check letter: an odd/even weighted-sum mod-26 over the 22-character ABI+CAB+account tail, mapped to a letter (`0`→`A` … `25`→`Z`). San Marino shares Italy's exact BBAN structure and algorithm. |

**Estonia (`EE`) is deliberately not covered.** Its real national check-digit algorithm depends on a
bank-specific, variable-length raw domestic account number that can't be reconstructed from the
fixed-width IBAN fields the registry exposes — shipping a generic implementation would incorrectly
reject real, valid Estonian IBANs. `checkNational: true` on an `EE` IBAN silently skips the national
check (same as any other country without a registered validator), which is the deliberately-correct
behavior here, not a gap to be filled later.

```php
use Daycry\Iban\Iban;

$iban = new Iban();

// BE: mod-97 of the first 10 BBAN digits
$iban->validate('BE68539007547034', checkNational: true)->isValid(); // true

// IT/SM share the CIN algorithm; FR/MC share the RIB key algorithm
$iban->validate('IT60X0542811101000000123456', checkNational: true)->isValid(); // true
$iban->validate('MC5811222000010123456789030', checkNational: true)->isValid();  // true

// EE: no registered validator — checkNational is a silent skip, not a failure
$iban->isValid('EE382200221020145685'); // true, national check never runs
```

To add or override a validator per instance (e.g. a custom country), construct `Core\Validator`
directly with your own `$nationalValidators` map — the constructor parameter is public API, not just
an implementation detail (see `src/Core/Validator.php`).

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
$parsed->nationalCheckDigit;  // '45' (structural extraction only — not the same as checkNational validation)
$parsed->sepaCountry;         // true — EPC409-09 country-level SEPA scope, from the registry
$parsed->electronic;          // 'ES9121000418450200051332' — canonical normalized form
(string) $parsed;              // same as ->electronic (ParsedIban::__toString())

$parsed->format(\Daycry\Iban\Enums\IbanFormat::Print); // 'ES91 2100 0418 4502 0005 1332'
```

## Formatting

See [`docs/formatting.md`](formatting.md) for the full reference, including the exact `Anonymized`
mask scheme.

## Validating a BIC/SWIFT

Added in v2.1. A **BIC** (ISO 9362, a.k.a. SWIFT code) is the 8- or 11-character bank identifier
(`AAAABBCC` or `AAAABBCCDDD`: 4-letter institution + 2-letter country + 2-char location + optional
3-char branch). The library validates, parses, and — given both an IBAN and a BIC — cross-checks them.
Like the IBAN core, **BIC validation works standalone with zero config and no database.**

Two honest limits, up front:

1. **A BIC has no checksum.** Unlike an IBAN's MOD-97 check digits, a BIC carries nothing you can
   arithmetically verify. So "valid" here means only *well-formed + its country code is a recognised
   ISO 3166-1 code* — it can **never** mean "this BIC exists / is live on the SWIFT network". Confirming
   a BIC is real needs a directory lookup (see [BIC resolution](#bic-resolution-needs-a-populated-banks-table)),
   not validation.
2. **The structural bank cross-check is only possible for some countries.** When you validate an IBAN
   *and* a BIC together, the country codes are always compared. But comparing the *bank* is only
   structurally sound for countries whose IBAN bank-code segment is exactly the BIC's 4-letter
   institution prefix. Against the current registry that's these **19** countries:
   `AZ, BG, BH, GB, GI, IE, IQ, JO, KW, LC, LV, MT, NL, PK, PS, QA, RO, SV, VG`. For every other
   country (ES, DE, FR, … — whose IBAN bank code is *numeric*) there is no structural relationship
   between the IBAN digits and the BIC letters, so only the country is cross-checked, never the bank.

### Mode 1 — validate a BIC on its own

```php
use Daycry\Iban\Iban;

$iban = new Iban(); // zero-config, no database

$iban->isValidBic('CAIXESBBXXX');   // true  (11-char, branch 'XXX')
$iban->isValidBic('DEUTDEFF');       // true  (8-char)
$iban->isValidBic('CHASUS33');       // true  (US — has no IBAN, but a perfectly valid BIC)
$iban->isValidBic('DEUTDEFF5');      // false (length is 9 — must be 8 or 11)

$result = $iban->validateBic('DEUTDEFF5'); // never throws
$result->isValid();                          // false
$result->firstViolation()->code;             // ViolationCode::BicBadLength
$result->firstViolation()->code->value;      // 'bic_bad_length'
$result->firstViolation()->messageKey;       // 'bic.violation.bad_length'
```

Parse a well-formed BIC into its structural parts:

```php
$bic = $iban->parseBic('NWBKGB2L');   // throws InvalidBicException if malformed
$bic->institutionCode;                 // 'NWBK'
$bic->countryCode;                     // 'GB'
$bic->locationCode;                    // '2L'
$bic->branchCode;                      // null (8-char BIC)
$bic->isPrimaryOffice();               // true
$bic->bic8();                          // 'NWBKGB2L'

$maybe = $iban->tryParseBic('nope');   // null instead of throwing
$iban->normalizeBic(' deutde ff ');    // 'DEUTDEFF'  (strip whitespace + uppercase; does not validate)
```

The BIC validation pipeline short-circuits on the first failure and reports one violation via a
`ViolationCode` case whose `->value` carries a `bic_` prefix so you can tell which field failed:

| `ViolationCode` | `->value` | Triggered when |
|---|---|---|
| `BicBlank` | `bic_blank` | Empty after normalization. |
| `BicBadLength` | `bic_bad_length` | Length is not exactly 8 or 11. |
| `BicIllegalCharacters` | `bic_illegal_characters` | A character outside `[A-Z0-9]` remains. |
| `BicMalformedStructure` | `bic_malformed_structure` | Right length/charset, wrong char class per position. |
| `BicUnknownCountry` | `bic_unknown_country` | Positions 5-6 aren't a recognised ISO 3166-1 code (the full ~249-code set, plus `XK`). |

> **Country codes:** a BIC's country (positions 5-6) is checked against the full ISO 3166-1 set (249
> officially assigned codes) — **not** the ~78 IBAN countries — so BICs from non-IBAN countries (US, JP,
> CN, …) validate. Kosovo's user-assigned `XK` is accepted too (real XK BICs exist); no other non-ISO
> code (`UK`/`EU`/`EL`/…) is.

### Mode 2 — validate an IBAN on its own

Nothing new here — this is just [`validate()` / `isValid()`](#validation-and-the-8-violationcode-cases)
from earlier in this guide.

### Mode 3 — validate both, with the cross-check

`validateIbanAndBic(?string $iban, ?string $bic)` is the "one, the other, or both" entry point. When
both are supplied **and each is individually valid**, it also cross-checks them for mutual coherence
(country always; bank only for the 19 countries above). It never throws.

```php
// Both valid AND coherent → valid, no violations
$iban->validateIbanAndBic('GB29NWBK60161331926819', 'NWBKGB2L')->isValid();   // true

// Country mismatch: a GB IBAN with a DE BIC
$r = $iban->validateIbanAndBic('GB29NWBK60161331926819', 'DEUTDEFF');
$r->isValid();                                   // false
$r->firstViolation()->code;                      // ViolationCode::BicIbanCountryMismatch

// Bank mismatch (GB is one of the 19 alpha-bank-code countries): same country, wrong institution
$r = $iban->validateIbanAndBic('GB29NWBK60161331926819', 'BARCGB22');
$r->firstViolation()->code;                      // ViolationCode::BicIbanBankMismatch

// ES uses a NUMERIC bank code → the bank is NOT cross-checked, only the country:
$iban->validateIbanAndBic('ES9121000418450200051332', 'CAIXESBBXXX')->isValid(); // true (country ES == ES; bank not compared)

// Neither supplied → a single NothingToValidate violation
$iban->validateIbanAndBic(null, '  ')->firstViolation()->code;  // ViolationCode::NothingToValidate
```

Two things to note. First, when both are supplied but one is structurally invalid, the cross-check is
**skipped** — you get that value's own violations only, never a manufactured mismatch. Second, unlike the
single-IBAN pipeline, this can return **more than one** violation at once (e.g. an invalid IBAN *and* an
invalid BIC, or both cross-check failures).

### With the helper

```php
helper('iban');

bic_is_valid('CAIXESBBXXX');            // true
bic_validate('DEUTDEFF5');               // ValidationResult (never throws)
bic_parse('NWBKGB2L');                   // ?ParsedBic (null if malformed — uses tryParseBic())
bic_format('  deutde ff ');              // 'DEUTDEFF'  (normalize only; does not validate)
iban_bic_validate('GB29NWBK60161331926819', 'NWBKGB2L'); // ValidationResult, incl. cross-check
iban_bic_validate(null, null);           // ValidationResult with a single NothingToValidate

// Resolution (needs a BIC-aware provider + populated banks table — see below):
bic_resolve('DEUTDEFF');                 // ?BankInfo  (null with the default NullProvider)
bic_bank_name('DEUTDEFF');               // ?string
```

Every BIC helper is degradation-safe — none of them throw (there is no BIC equivalent of the throwing
`iban_resolve()`).

### With the CLI

Two entry points. `iban:bic` is BIC-only (validate + parse + resolve); `iban:validate --bic` covers all
three modes.

```bash
# Dedicated BIC command — validates, parses, and (if a provider can) resolves.
$ php spark iban:bic NWBKGB2L
# → a Field/Value table; exit 0 for a valid BIC, 1 otherwise.

$ php spark iban:bic DEUTDEFF5 --json
{
    "valid": false,
    "violation": { "code": "bic_bad_length", "message": "The BIC must be 8 or 11 characters long." }
}

# iban:validate with --bic switches to the combined entry point. The <iban> arg is now OPTIONAL.
$ php spark iban:validate --bic=NWBKGB2L                         # BIC only (no IBAN)
$ php spark iban:validate GB29NWBK60161331926819 --bic=NWBKGB2L   # both + cross-check
$ php spark iban:validate GB29NWBK60161331926819                  # IBAN only (no --bic; byte-identical to before)
```

Without `--bic`, `iban:validate` behaves exactly as it always has (single-violation output, singular
`"violation"` JSON key). With `--bic`, the combined mode can print multiple violations and uses a plural
`"violations"` JSON array. Both `iban:bic` and `iban:validate` exit `0` when valid, `1` otherwise — safe
to use directly in shell scripts/CI. See [spark commands](#spark-commands) for the full flag reference.

### Choosing the ISO 3166 source

BIC country-code validation reads from an `IsoCountryRegistry`. By default this is the **bundled compiled
list** — zero setup, no database — and that's what you want in almost every case:

```php
// Config\Iban (default)
public string $isoCountrySource = 'php';        // the compiled list; nothing to install
```

You can instead read the country set from a database table, `iso_countries`:

```php
// app/Config/Iban.php
public string $isoCountrySource = 'database';
public string $isoCountryTable  = 'iso_countries';
```

```bash
php spark migrate -n "Daycry\Iban"                                          # creates iso_countries
php spark db:seed "Daycry\Iban\Database\Seeds\IsoCountriesSeeder"           # populates it from the compiled list
```

The seeder upserts on the unique `alpha2` column, so it's safe to re-run. An empty/missing table yields
an **empty** registry (every BIC would then fail with `BicUnknownCountry`), so only switch to `'database'`
after seeding. **When would you want this at all?** Rarely — only if you need to *curate* the accepted
country set in the database (add or remove codes) rather than accept the bundled 249. Any
`$isoCountrySource` value other than `'database'` is treated as `'php'`.

## Resolving bank data: `NullProvider` vs `DatabaseProvider`

`resolve()` always returns a `BankResult` — a `ParsedIban` plus 12 nullable bank-data fields
(`bankName`, `shortName`, `bic`, `city`, `address`, `sepaSct`, `sepaSctInst`, `sepaSddCore`,
`sepaSddB2b`, `sourceId`, `sourceVersion`, `sourceLicense`), a 13th nullable provenance field
(`resolvedBy` — see [below](#knowing-whether-a-result-came-from-your-database-or-the-ibancom-fallback)),
and `isResolved(): bool` (`true` iff any of the 12 bank-data fields is non-null; `resolvedBy` is
deliberately excluded from that check since it's metadata, not bank data).

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
   the `Daycry\Iban\` Composer namespace. CI4's `migrate` command only recognizes the short `-n` flag
   for a namespace (there is no `--namespace` long alias — passing that instead silently runs zero
   package migrations):

   ```bash
   php spark migrate -n "Daycry\Iban"
   # or, to run every discovered namespace's pending migrations:
   php spark migrate --all
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

   To resolve real bank data, seed the `banks` table yourself (e.g. from your own licensed source), or
   run one of the v1.1 bundled importers via `iban:update` — see
   [Bank-data importers (`iban:update`)](#bank-data-importers-ibanupdate) and
   [`docs/importers.md`](importers.md).

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

### BIC resolution needs a populated `banks` table

`resolveBic(string|ParsedBic $bic): ?BankInfo` (on both the facade and the `Resolver`) resolves a bank
straight from a BIC. It's the resolution counterpart to BIC *validation* — and it comes with the same
honest caveat: a BIC has no checksum, so this can only ever return data the configured provider actually
holds; it is not a proof of existence.

Two conditions must both hold for `resolveBic()` to return a non-null `BankInfo`:

1. **A BIC-aware provider.** `resolveBic()` consults the provider only if it implements
   [`BicProviderInterface`](../src/Contracts/BicProviderInterface.php). The default `NullProvider`
   does **not**, so out of the box `resolveBic()` always returns `null`. The providers that DO support
   it are `DatabaseProvider` (local `banks` table), `ChainProvider`, `CachedProvider` (both delegate to
   their inner provider iff it's BIC-aware), and `IbanComProvider` (via the iban.com BIC/SWIFT API,
   opt-in with an API key). A malformed BIC short-circuits to `null` without ever touching the provider.

2. **BIC data in the `banks` table** (for the `DatabaseProvider` path). The `banks` table ships empty;
   you populate it with `iban:update` (see [Bank-data importers](#bank-data-importers-ibanupdate)), and
   only importers whose source *publishes a BIC column* fill `banks.bic`. Matching is by **BIC8** (the
   first 8 characters — the institution's primary office), so an 8-char query resolves an 11-char stored
   row and vice-versa; input is normalized (uppercased, whitespace-stripped) first.

**Which bundled importers actually supply BICs.** Of the 30 bundled importers, these populate `banks.bic`
(so `resolveBic()` can find a bank from their data): **CH & LI** (`six`), **DE** (`bundesbank`), **NL**
(`betaalvereniging`), **BE** (`nbb`), **CZ** (`cnb`), **SK** (`nbs`), **SI** (`bsi`), **HR** (`hnb`),
**HU** (`mnb`), **MT** (`cbm`), **BG** (`bnb`), **AZ** (`cbar`), **MD** (`bnm`), **NO** (`bits`),
**GE** (`nbg`), **KZ** (`nbk`), **LU** (`abbl`), and the **EPC SEPA Register** (`epc`, for GB/GI/IE/LV/RO —
whose IBAN bank code IS the BIC prefix). The remaining importers publish only a bank code with **no** BIC
column, so their rows can't answer a `resolveBic()` lookup: **AT** (`oenb`), **ES** (`bde`), **GR** (`hba`),
**PL** (`nbp`), **IL** (`boi`), **UA** (`nbu`), and **BR** (`bcb`). (These datasets still resolve fine by
*IBAN* via `resolve()` — they just don't carry the BIC needed for the reverse, BIC-first lookup.) See the
[bundled-importer table in `docs/importers.md`](importers.md#the-30-bundled-importers) for each source's
full column layout.

```php
// Default NullProvider — resolveBic() always null, no matter the BIC:
(new Iban())->resolveBic('DEUTDEFF');   // null

// With Config\Iban::$provider = 'database' and a DE row seeded (e.g. via `iban:update --source=bundesbank`):
$info = service('iban')->resolveBic('DEUTDEFF');   // ?BankInfo
$info?->bankName;                                    // e.g. 'Deutsche Bank'
$info?->resolvedBy;                                  // 'database'
```

## Caching resolved lookups: `CachedProvider`

`Providers\CachedProvider` is a `ProviderInterface` decorator that caches `findByBankCode()`/
`findByIban()` results behind a CI4 `CacheInterface`, so repeated lookups of the same bank/branch code
don't re-query the decorated provider (e.g. a `DatabaseProvider` hitting the `banks` table on every
`resolve()` call for the same IBAN). It caches **misses too**, via an internal sentinel value — a
lookup that resolves to nothing is remembered just as eagerly as one that resolves to a `BankInfo`, so
repeatedly resolving an unregistered bank code doesn't keep re-querying the underlying provider.

It's entirely opt-in and CI4-only, wired by `Config\Services::iban()`, not something you construct by
hand in ordinary use:

```php
// app/Config/Iban.php
namespace Config;

class Iban extends \Daycry\Iban\Config\Iban
{
    public string $provider = 'database';
    public ?int $cacheTtl   = 3600; // seconds; null (default) disables caching entirely
}
```

`$cacheTtl` is nullable, with three distinct meanings:

| Value | Effect |
| --- | --- |
| `null` (the default) | Caching disabled — `service('iban')` leaves the resolved provider unwrapped, identical to a package with no cache at all. |
| `0` | The provider IS wrapped in `CachedProvider`, with a TTL of `0` passed straight to `service('cache')` — CI4's cache handlers treat `0` as **"never expires"**, not "already expired"/"disabled". |
| `> 0` | Wraps the provider with this TTL in seconds. |

`NullProvider` is never wrapped regardless of `$cacheTtl` — it never resolves anything, so caching it
would just add a pointless cache round-trip to every `resolve()` call.

> **BREAKING CHANGE (migrating from < v2.0)**: `$cacheTtl` used to be a plain `int` defaulting to `0`,
> and `0` meant "caching disabled". That collided with CI4's own convention — where a cache TTL of `0`
> means "never expires" — and left no way to actually configure a never-expiring cache. As of v2.0,
> `$cacheTtl` is `?int` and **`null` is now the "disabled" value**. If you previously set
> `$cacheTtl = 0` to disable caching, change it to `$cacheTtl = null`; if you left it at the old
> default (`0`), your cache is now enabled with a never-expiring TTL and you should set it explicitly
> (`null` to keep caching off, or a real TTL in seconds) rather than relying on the default.
>
> **Cached-miss warning**: `CachedProvider` caches misses too (a lookup that resolves to nothing is
> remembered just like a hit — see below). With `$cacheTtl = 0` a miss is therefore cached **forever**.
> Concretely: after running `php spark iban:update` to import new bank data, any IBAN/bank code that
> was a cached miss before the import stays a permanent miss afterward unless the cache is cleared —
> always run `php spark cache:clear` after `iban:update` when caching is enabled (any `$cacheTtl` other
> than `null`, but especially `0`).

## iban.com fallback: `IbanComProvider` + `ChainProvider`

`Providers\IbanComProvider` is an **opt-in, last-resort** `ProviderInterface` backed by the
[iban.com Validation API](https://www.iban.com/validation-api) (`POST
https://api.iban.com/clients/api/v4/iban/`) — a paid, external, third-party service. It's disabled by
default; set `Config\Iban::$ibanComApiKey` (or the `iban.ibanComApiKey` `.env` key) to enable it:

```php
// app/Config/Iban.php
namespace Config;

class Iban extends \Daycry\Iban\Config\Iban
{
    public string $provider       = 'database';
    public string $ibanComApiKey  = ''; // set via .env, never commit a real key
    public int $ibanComTimeout    = 5;  // seconds
}
```

```bash
# .env
iban.ibanComApiKey = 'your-real-api-key-here'
```

With a non-empty `$ibanComApiKey`, `service('iban')` chains an `IbanComProvider` **after** the
primary provider (`$provider`, e.g. `DatabaseProvider`) via `Providers\ChainProvider` — a small
`ProviderInterface` composite that tries an ordered list of providers and returns the first non-null
result. In practice this means: the local `banks` table (or whatever `$provider` is configured to) is
always tried first; iban.com is only queried when the primary provider has nothing for that IBAN. With
the default `$ibanComApiKey = ''`, no chaining happens at all — behavior is identical to a package with
no knowledge of iban.com.

```php
$bank = $iban->resolve('DE89370400440532013000');
// 1. DatabaseProvider (or whatever $provider is set to) is tried first.
// 2. Only if that resolves to nothing, IbanComProvider queries the iban.com API.
```

If `Config\Iban::$cacheTtl` is non-`null`, the combined chain (local lookup + iban.com fallback) is
wrapped in `CachedProvider` as usual — a `ChainProvider` is never a `NullProvider`, so it caches like
any other provider, meaning a successful iban.com response is cached exactly like a local one and isn't
re-fetched on every `resolve()` call for the same IBAN.

**`IbanComProvider::findByIban()` never throws.** A DNS failure, timeout, non-200 response, malformed
JSON, a non-empty `errors` array, or an empty/missing `bank_data` in the response all fold to `null`, so
`resolve()` degrades to an unresolved `BankResult` instead of ever propagating a network failure.
`findByBankCode()` always returns `null` — the iban.com API resolves a full IBAN, not a bare bank code,
so `findByIban()` is the only useful entry point.

**Privacy and cost note:** every fallback lookup sends the full IBAN to iban.com over the network, and
the API is a paid, metered third-party service — only enable it if you've reviewed iban.com's terms and
are comfortable with IBANs leaving your infrastructure for unresolved lookups.

### Knowing whether a result came from your database or the iban.com fallback

`BankResult::$resolvedBy` (and `BankInfo::$resolvedBy`) tells you WHICH provider actually answered a
`resolve()` call — `'database'` for the local `banks` table, `'iban.com'` for the remote API fallback, or
a custom provider's own id. It's `null` when nothing resolved (e.g. the default `NullProvider`, or a
`ChainProvider` where every chained provider missed).

```php
$bank = $iban->resolve('DE89370400440532013000');

if ($bank->resolvedBy === 'database') {
    // Answered from your own banks table — free, no network call.
} elseif ($bank->resolvedBy === 'iban.com') {
    // Answered by the paid iban.com fallback — the local DB had nothing for this IBAN.
}
```

This is distinct from `$bank->sourceId`, which identifies the *dataset* the row came from (e.g.
`'epc'`, `'bde'`, or `'iban.com'`), not which provider served this particular lookup. A
`DatabaseProvider` row imported from the iban.com API in the past carries `sourceId: 'iban.com'` but
still reports `resolvedBy: 'database'`, because a local DB lookup — not a live API call — is what
answered *this* `resolve()` call:

```php
$bank->sourceId;   // 'iban.com'  -- which DATASET this row's data came from
$bank->resolvedBy; // 'database'  -- which PROVIDER answered THIS lookup (a local DB read)
```

## `Config\Iban`

Publishable, `.env`-overridable configuration (`Daycry\Iban\Config\Iban`, `BaseConfig` subclass). Every
property is overridable via `.env` using the `iban.<property>` prefix, e.g. `iban.provider = database`.

| Property | Default | Meaning |
|---|---|---|
| `$provider` | `'null'` | `'null'` (no lookups — the safe default), `'database'` (`DatabaseProvider`), or the fully-qualified class name of a custom `ProviderInterface` implementation. |
| `$defaultFormat` | `'print'` | `'electronic'`, `'print'`, or `'anonymized'` — the default format used by the [`iban_format()` helper](#the-iban_helper) when its caller doesn't pass a `$format` argument. The facade's own `format()` is a separate, frozen contract: it keeps its own explicit `IbanFormat $f = IbanFormat::Print` default and never reads this config — the config is consulted only at the CI4 helper layer. |
| `$checkNationalByDefault` | `false` | Whether national check-digit validation runs by default when a caller doesn't pass an explicit flag — consulted by the [`iban_validate()`/`iban_is_valid()`/`iban_valid()` helpers](#the-iban_helper) and by the [`iban:validate` command](#spark-commands) when `--national` is omitted. The facade's own `validate()` is a separate, frozen contract: it keeps its own explicit `bool $checkNational = false` parameter default and never reads this config. |
| `$dbGroup` | `null` | The `Config\Database` connection group queried by `DatabaseProvider` / `BankModel` — wired by `Config\Services::iban()`'s `'database'` branch, which builds `new BankModel($config->table, $config->dbGroup)`. `null` means "no override": `BankModel` leaves its own `$DBGroup` unset, so CI4's environment-aware fallback applies transparently (`Database\Config::connect(null)` resolves to `'tests'` when `ENVIRONMENT === 'testing'`, otherwise the app's `Config\Database::$defaultGroup`). Set this only to force a specific connection group regardless of environment (e.g. a read replica). |
| `$table` | `'banks'` | The table name queried by `DatabaseProvider` / `BankModel` — wired the same way as `$dbGroup` above. |
| `$cacheTtl` | `null` | Cache TTL, in seconds, for resolved bank lookups (see [`CachedProvider`](#caching-resolved-lookups-cachedprovider)). `null` disables caching: the resolver's provider is left unwrapped. `0` wraps the provider (except `NullProvider`) in a `CachedProvider` backed by `service('cache')` with a TTL of `0`, which CI4 treats as **never expires**. Any value `> 0` wraps it with that TTL in seconds. **BREAKING as of v2.0**: `0` used to mean "disabled"; `null` does now — see the migration note above. |
| `$ibanComApiKey` | `''` | Opt-in API key for the [iban.com fallback](#ibancom-fallback-ibancomprovider--chainprovider). Empty (the default) disables it entirely; non-empty chains an `IbanComProvider` after the primary provider via `ChainProvider`. |
| `$ibanComTimeout` | `5` | Request timeout, in seconds, for `IbanComProvider`'s HTTP call to the iban.com Validation API. Only relevant when `$ibanComApiKey` is non-empty. |
| `$isoCountrySource` | `'php'` | Source for the ISO 3166-1 country registry used by BIC validation. `'php'` (default) = the bundled compiled list, no setup; `'database'` = the `iso_countries` table (run the migration + seed it first). See [Choosing the ISO 3166 source](#choosing-the-iso-3166-source). Any value other than `'database'` is treated as `'php'`. |
| `$isoCountryTable` | `'iso_countries'` | Table queried when `$isoCountrySource` is `'database'`. |

To override, publish your own `App\Config\Iban extends \Daycry\Iban\Config\Iban` (CI4's `Factories`
resolution picks up the app's version automatically), or set the matching `.env` variables.

## The `iban_helper`

Procedural convenience wrappers around `service('iban')`. Load it with `helper('iban')` (or add
`'iban'` to `Config\Autoload::$helpers` for an always-on helper). Every function is guarded by
`function_exists()`, so re-inclusion never fatals.

| Function | Signature | Behavior |
|---|---|---|
| `iban_validate()` | `(string $iban, ?bool $checkNational = null): ValidationResult` | Delegates to `validate()`. Never throws. `$checkNational === null` (the default) falls back to `Config\Iban::$checkNationalByDefault`; an explicit `true`/`false` always overrides the config. |
| `iban_is_valid()` | `(string $iban, ?bool $checkNational = null): bool` | Unlike the facade's own `isValid()` (which, by frozen contract, takes no `$checkNational` parameter), this delegates to `iban_validate()`'s `->isValid()`, so it shares the exact same `$checkNational`/config-default behavior. Never throws. |
| `iban_parse()` | `(string $iban): ?ParsedIban` | Always uses `tryParse()` under the hood — returns `null` instead of throwing, unlike the facade's own `parse()`. |
| `iban_format()` | `(string $iban, ?string $format = null): string` | `$format` is `'electronic'`, `'print'`, or `'anonymized'` (case-insensitive); anything else falls back to `'print'`. `$format === null` (the default) falls back to `Config\Iban::$defaultFormat`; an explicit value always overrides the config. |
| `iban_resolve()` | `(string $iban): BankResult` | Delegates straight to `resolve()` — **not** degradation-safe: throws `InvalidIbanException` for an invalid IBAN, same as the facade. |
| `bank_name()` | `(string $iban): ?string` | Safe: `null` for an invalid IBAN or an unresolved entity. Never throws. |
| `bank_bic()` | `(string $iban): ?string` | Same safety guarantee as `bank_name()`. |
| `iban_country()` | `(string $iban): ?string` | `null` for an invalid IBAN. Never throws. |
| `iban_valid()` | `(string $iban, ?bool $checkNational = null): bool` | Alias of `iban_is_valid()`, same `$checkNational`/config-default behavior. |
| `bic_validate()` | `(string $bic): ValidationResult` | Delegates to `validateBic()`. Never throws. |
| `bic_is_valid()` | `(string $bic): bool` | Delegates to `isValidBic()`. Never throws. |
| `bic_parse()` | `(string $bic): ?ParsedBic` | Uses `tryParseBic()` — `null` for a malformed BIC instead of throwing. |
| `bic_format()` | `(string $bic): string` | Normalizes (uppercase, whitespace-stripped) via `normalizeBic()`. A BIC has one canonical form, so no `$format` arg. Does **not** validate. |
| `bic_resolve()` | `(string $bic): ?BankInfo` | Delegates to `resolveBic()`. Degradation-safe: `null` on a malformed BIC or an unresolved/BIC-unaware provider. Never throws. |
| `bic_bank_name()` | `(string $bic): ?string` | Safe: `null` for a malformed BIC or unresolved entity. Never throws. |
| `iban_bic_validate()` | `(?string $iban, ?string $bic): ValidationResult` | Delegates to `validateIbanAndBic()` (IBAN only / BIC only / both + cross-check). Both `null`/blank → a single `ViolationCode::NothingToValidate`. Never throws. |

```php
helper('iban');

iban_validate('ES9121000418450200051332');       // ValidationResult{valid: true, violations: []}
iban_is_valid('ES9121000418450200051332');         // true
iban_valid('ES9121000418450200051332');             // true (alias)
iban_parse('basura');                                 // null
iban_format('ES9121000418450200051332', 'ELECTRONIC'); // 'ES9121000418450200051332' (case-insensitive)
iban_format('ES9121000418450200051332');               // config Config\Iban::$defaultFormat when omitted ('print' by default)
iban_country('ES9121000418450200051332');            // 'ES'
bank_name('ES9121000418450200051332');                // null (NullProvider / empty banks table)
bank_bic('ES9121000418450200051332');                  // null, same reason
```

## spark commands

All 6 commands are grouped under `IBAN` in `php spark list` and are auto-discovered — no manual
registration needed. Where a command takes an `<iban>`/`<bic>` argument it's optional on the command
line: if omitted, the command falls back to an interactive `CLI::prompt(...)` instead of failing
outright (except `iban:validate --bic` with no `<iban>`, which is the deliberate BIC-only mode and does
not prompt).

### `iban:validate [<iban>] [--national] [--json] [--bic=<bic>]`

Thin wrapper over `service('iban')->validate()` — or, with `--bic`, over
`service('iban')->validateIbanAndBic()` (the combined IBAN+BIC entry point).

| Field | Description |
|---|---|
| Argument | `iban` — the IBAN to validate. **Optional when `--bic` is given** (BIC-only mode). |
| `--national` | Also run the country-specific national check-digit validator, if one is registered. When omitted, the effective value comes from `Config\Iban::$checkNationalByDefault` (default `false`); passing `--national` explicitly always forces it `true` regardless of the config. Ignored in combined `--bic` mode. |
| `--json` | Emit the result as JSON instead of colored CLI text. |
| `--bic=<bic>` | Switches to the combined entry point: also validate this BIC and, together with a valid IBAN, cross-check the two. Given alone (no `<iban>`), validates just the BIC. |
| Output | Without `--bic`: `VALID` / `INVALID: <code> - <message>`, or a `{"valid": ..., "violation": ...}` object (singular `violation`) for `--json`. With `--bic`: the same, except the combined result can carry **multiple** violations — every one is printed, and `--json` emits a plural `"violations"` array. |
| Exit code | `0` (`EXIT_SUCCESS`) when valid, `1` (`EXIT_ERROR`) otherwise — safe to use directly in shell scripts/CI. |

The three combined modes:

```bash
$ php spark iban:validate GB29NWBK60161331926819                  # IBAN only (no --bic; identical to before)
VALID

$ php spark iban:validate --bic=NWBKGB2L                          # BIC only (no IBAN)
VALID

$ php spark iban:validate GB29NWBK60161331926819 --bic=DEUTDEFF   # both + cross-check
INVALID: bic_iban_country_mismatch - The BIC country code does not match the IBAN country code.
INVALID: bic_iban_bank_mismatch - The BIC institution code does not match the IBAN bank code.

$ php spark iban:validate --bic=NWBKGB2L --json
{
    "valid": true,
    "violations": []
}
```

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

$ php spark iban:validate ES9121000418450200051332 --json
{
    "valid": true,
    "violation": null
}

$ php spark iban:validate ES2921000418460200051332 --national --json
{
    "valid": false,
    "violation": {
        "code": "national_check_failed",
        "message": "The national check digits are invalid."
    }
}
```

### `iban:parse <iban> [--json]`

Wraps `tryParse()`. Prints a CLI table of the 9 `ParsedIban` fields by default, or `--json` for
machine-readable output.

| Field | Description |
|---|---|
| Argument | `iban` — the IBAN to parse. |
| `--json` | Emit the parsed fields as JSON instead of a CLI table. |
| Output | A `Field`/`Value` CLI table by default (booleans/`null` stringified as `'true'`/`'false'`/`''`), or the 9 fields as a JSON object with real types (`sepaCountry` a JSON boolean, absent fields `null`). |
| Exit code | `0` on success; on an invalid IBAN, prints `CLI::error('Invalid IBAN')` (no exception thrown) and exits `1`. |

```bash
$ php spark iban:parse ES9121000418450200051332
+---------------------+---------------------------+
| Field               | Value                     |
+---------------------+---------------------------+
| countryCode         | ES                        |
| checkDigits         | 91                        |
| bban                | 21000418450200051332      |
| bankIdentifier      | 2100                      |
| branchIdentifier    | 0418                      |
| accountNumber       | 0200051332                |
| nationalCheckDigit  | 45                        |
| sepaCountry         | true                      |
| electronic          | ES9121000418450200051332  |
+---------------------+---------------------------+

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

$ php spark iban:parse ES9021000418450200051332
Invalid IBAN
```

### `iban:resolve <iban> [--json]`

Wraps `resolve()`. Prints the IBAN's structural fields (excluding `nationalCheckDigit` and
`sepaCountry` — use `iban:parse` for those) plus the 12 bank-data fields, `resolvedBy`, and `isResolved`.

| Field | Description |
|---|---|
| Argument | `iban` — the IBAN to resolve. |
| `--json` | Emit the result as JSON instead of a CLI table. |
| Output | A `Field`/`Value` CLI table (with a yellow `Note: no provider data (empty bank DB) — structural fields only.` line whenever `isResolved` is `false`), or the 20 fields as a JSON object. |
| Exit code | `0` on success; on an invalid IBAN, prints `CLI::error('Invalid IBAN')` and exits `1`. |

```bash
$ php spark iban:resolve ES9121000418450200051332
+-------------------+---------------------------+
| Field             | Value                     |
+-------------------+---------------------------+
| countryCode       | ES                        |
| checkDigits       | 91                        |
| bban              | 21000418450200051332      |
| bankIdentifier    | 2100                      |
| branchIdentifier  | 0418                      |
| accountNumber     | 0200051332                |
| electronic        | ES9121000418450200051332  |
| bankName          |                           |
| shortName         |                           |
| bic               |                           |
| city              |                           |
| address           |                           |
| sepaSct           |                           |
| sepaSctInst       |                           |
| sepaSddCore       |                           |
| sepaSddB2b        |                           |
| sourceId          |                           |
| sourceVersion     |                           |
| sourceLicense     |                           |
| resolvedBy        |                           |
| isResolved        | false                     |
+-------------------+---------------------------+
Note: no provider data (empty bank DB) — structural fields only.

$ php spark iban:resolve ES9121000418450200051332 --json
{
    "countryCode": "ES",
    "checkDigits": "91",
    "bban": "21000418450200051332",
    "bankIdentifier": "2100",
    "branchIdentifier": "0418",
    "accountNumber": "0200051332",
    "electronic": "ES9121000418450200051332",
    "bankName": null,
    "shortName": null,
    "bic": null,
    "city": null,
    "address": null,
    "sepaSct": null,
    "sepaSctInst": null,
    "sepaSddCore": null,
    "sepaSddB2b": null,
    "sourceId": null,
    "sourceVersion": null,
    "sourceLicense": null,
    "resolvedBy": null,
    "isResolved": false
}
```

(With `Config\Iban::$provider = 'database'` and a matching row seeded in `banks` — e.g. via
`iban:update` — the bank-data fields and `isResolved` populate instead; see
[Resolving bank data](#resolving-bank-data-nullprovider-vs-databaseprovider) above.)

### `iban:bic <bic> [--json]`

Validates and parses a BIC (ISO 9362 / SWIFT), then — when the configured provider can — resolves the
bank behind it (`resolveBic()`). A BIC has no checksum, so "valid" means well-formed with a recognised
country code, never that it exists on the live SWIFT network; bank fields populate only when a
BIC-aware provider (e.g. `database`) resolves it — with the default empty DB they stay blank and a
yellow note is printed. See [Validating a BIC/SWIFT](#validating-a-bicswift) and
[BIC resolution](#bic-resolution-needs-a-populated-banks-table).

| Field | Description |
|---|---|
| Argument | `bic` — the BIC to validate, parse, and resolve. If omitted, prompts via `CLI::prompt('BIC')`. |
| `--json` | Emit the result as JSON instead of a CLI table. |
| Output | For an invalid BIC: `INVALID: <code> - <message>` (or `{"valid": false, "violation": {...}}` for `--json`). For a valid BIC: a `Field`/`Value` table of the parsed fields (`bic`, `institutionCode`, `countryCode`, `locationCode`, `branchCode`, `primaryOffice`) plus any resolved bank fields and `resolved`, or the same as a JSON object. |
| Exit code | `0` for a valid BIC, `1` otherwise. |

```bash
$ php spark iban:bic NWBKGB2L --json
{
    "valid": true,
    "bic": "NWBKGB2L",
    "institutionCode": "NWBK",
    "countryCode": "GB",
    "locationCode": "2L",
    "branchCode": null,
    "primaryOffice": true,
    "bankName": null,
    "shortName": null,
    "city": null,
    "address": null,
    "sourceId": null,
    "sourceVersion": null,
    "sourceLicense": null,
    "resolvedBy": null,
    "resolved": false
}

$ php spark iban:bic DEUTDEFF5
INVALID: bic_bad_length - The BIC must be 8 or 11 characters long.
```

### `iban:update [--all] [--country=<cc>] [--source=<id>] [--dry-run] [--file=<path>]`

Lists or runs the bundled bank-data importers against the `banks` table, printing licensing notices
plus an import report per source.

| Option | Meaning |
|---|---|
| `--all` | Run every bundled importer (all 30 countries/sources) in one invocation. Fetches live; combine with `--dry-run` to preview. **Cannot** be combined with `--file` (a single local file can't feed many importers) — that combination prints an error and exits `1` immediately. Combine with `--country=` to narrow to just that country's importer(s) instead of all 30 (`--source=` is ignored under `--all`). |
| `--country=<cc>` | Restrict to importers for this ISO 3166-1 alpha-2 country code (e.g. `AT`). |
| `--source=<id>` | Restrict to the importer with this source id (e.g. `oenb`). |
| `--dry-run` | Preview: count what would be imported/skipped without writing to the `banks` table. |
| `--file=<path>` | Import offline from this local file instead of fetching from the source live. |

**Exit codes**: `0` in every normal case — including "no importer matched the selection" and the
no-selection listing — except `--all` combined with `--file`, which exits `1`.

**Workflow**:

1. **Configure the database provider and create the `banks` table** — set
   `Config\Iban::$provider = 'database'` (see [`Config\Iban`](#configiban) above), then run the
   package's migration:

   ```bash
   php spark migrate -n "Daycry\Iban"
   ```

2. **List what's bundled** — with no `--all`/`--country`/`--source` at all, the command only lists the
   registered importers; nothing runs:

   ```bash
   $ php spark iban:update
   SWIFT IBAN Registry is non-commercial/no-derivatives (not bundled).
   SWIFT BIC Directory (SwiftRef) is proprietary (not bundled).
   National lists require per-source attribution.
   Registered importers: 30
   +---------+------------------+--------------------------------+-------------------------------+
   | Country | Source           | Name                           | License                        |
   +---------+------------------+--------------------------------+-------------------------------+
   | AT      | oenb             | Oesterreichische Nationalbank  | CC-BY-4.0 (OeNB)                |
   | DE      | bundesbank       | Deutsche Bundesbank            | Deutsche Bundesbank             |
   | ...     | ...              | ... (28 more — see docs/importers.md) | ...                       |
   +---------+------------------+--------------------------------+-------------------------------+
   Select one with --country=/--source= to run it (add --dry-run to preview).
   ```

3. **Run one importer** — select by `--country`, `--source`, or both; add `--file=<path>` for an
   offline import, or `--dry-run` to preview counts without writing anything:

   ```bash
   $ php spark iban:update --source=oenb
   [AT/oenb] fetched=<N> imported=<N> skipped=0
   Source: Oesterreichische Nationalbank — https://www.oenb.at/docroot/downloads_observ/bankstellenverzeichnis.csv (CC-BY-4.0 (OeNB))

   $ php spark iban:update --country=AT --dry-run
   [AT/oenb] fetched=<N> imported=<N> skipped=0 (dry-run — nothing written)
   Source: Oesterreichische Nationalbank — https://www.oenb.at/docroot/downloads_observ/bankstellenverzeichnis.csv (CC-BY-4.0 (OeNB))

   $ php spark iban:update --source=six --file=/path/to/bankmaster_V3.csv
   [CH/six] fetched=... imported=... skipped=...
   Source: SIX Interbank Clearing — https://api.six-group.com/api/epcd/bankmaster/v3/bankmaster_V3.csv (SIX Interbank Clearing (free use))
   ```

   A selection matching no registered importer prints `No bundled importer matches that selection.`
   and exits `0` (not an error).

4. **Run every bundled importer at once** — `--all` runs all 30 (or, combined with `--country`, only
   that country's importer(s)) in a single invocation, live by default:

   ```bash
   $ php spark iban:update --all --dry-run
   SWIFT IBAN Registry is non-commercial/no-derivatives (not bundled).
   SWIFT BIC Directory (SwiftRef) is proprietary (not bundled).
   National lists require per-source attribution.
   [AT/oenb] fetched=<N> imported=<N> skipped=0 (dry-run — nothing written)
   Source: Oesterreichische Nationalbank — ...
   [DE/bundesbank] fetched=<N> imported=<N> skipped=0 (dry-run — nothing written)
   Source: Deutsche Bundesbank — ...
   ...
   [GB/epc] fetched=<N> imported=<N> skipped=0 (dry-run — nothing written)
   Source: European Payments Council (SEPA Register) — ...
   Ran 30 importers: <N> with rows, <N> empty, <N> failed. (dry-run)

   $ php spark iban:update --all --country=GB --dry-run
   ...
   [GB/epc] fetched=<N> imported=<N> skipped=0 (dry-run — nothing written)
   Ran 1 importers: <N> with rows, <N> empty, <N> failed. (dry-run)

   $ php spark iban:update --all --file=/path/to/x.csv
   --all cannot be combined with --file (a single local file cannot feed multiple importers).
   ```

   Each importer's run is isolated in its own `try`/`catch`, so one failure (an unreachable portal, a
   DB hiccup, ...) doesn't abort the rest — the closing `Ran N importers: ... with rows, ... empty,
   ... failed.` line is an aggregate summary across all of them, not a guarantee every source
   succeeded; a failed source is also reported inline (`CLI::error('[CC/source] failed: <message>')`).

   > **`--all` is a heavy, live operation.** It fetches every reachable source over the network in a
   > single run (the EPC register is fetched once per registered EPC country, i.e. five times), so run
   > it with an adequate `memory_limit` / `max_execution_time` and expect it to take a while. The five
   > portal/landing-page sources noted below (`abbl`, `bits`, `nbg`, `nbu`, `nbk`) come back **empty**
   > under `--all` (there is no `--file` to read) — import those per country with `--file=`. Use `--all`
   > for an initial bulk populate; prefer targeted per-country runs for a scheduled/repeatable job.

5. **Re-runs upsert, they don't duplicate** — `ImportRunner` upserts every yielded row by the natural
   key `(country_code, bank_code, branch_code)`, so running the same importer again updates existing
   rows in place instead of inserting duplicates. Every written row is stamped with `source_id`,
   `source_version` (the run's date) and `source_license` — see
   [Provenance](importers.md#provenance-how-imported-data-is-stored) in `docs/importers.md`. A few
   sources have no stable, direct live URL (a landing page or portal instead of a fetchable file) and
   are only reliably imported offline: **LU** (`abbl`), **NO** (`bits`), **GE** (`nbg`), **UA** (`nbu`)
   and **KZ** (`nbk`) all need `--file=<path>` in practice — see
   [`.xlsx` sources and offline-only imports](importers.md#xlsx-sources-and-offline-only-imports) for
   why, per source.

**All 30 `--source=` values at a glance** (`country=source`) — full publisher/format/license detail is
in [the bundled-importer table in `docs/importers.md`](importers.md#the-30-bundled-importers):

```
AT=oenb  DE=bundesbank  CH=six  NL=betaalvereniging  ES=bde  CZ=cnb  GR=hba  SI=bsi  SK=nbs  BG=bnb
MD=bnm  PL=nbp  AZ=cbar  BE=nbb  HR=hnb  LU=abbl  MT=cbm  HU=mnb  NO=bits  GE=nbg  IL=boi  UA=nbu
KZ=nbk  LI=six  BR=bcb  GB=epc  GI=epc  IE=epc  LV=epc  RO=epc
```

`--source=six` is shared by CH and LI (same source file, filtered by country); `--source=epc` is
shared by GB/GI/IE/LV/RO (same importer class, parameterized per country) — disambiguate either with
`--country=`.

### `iban:publish [--force]`

Publishes the package's `Config\Iban` into the consuming app as `app/Config/Iban.php` — the same
idiomatic CI4 "publish a config" pattern used by packages like `daycry/auth`/Shield.

```bash
$ php spark iban:publish
Published Iban config to app/Config/Iban.php

$ php spark iban:publish
app/Config/Iban.php already exists — use --force to overwrite.

$ php spark iban:publish --force
Published Iban config to app/Config/Iban.php
```

| Option | Meaning |
|---|---|
| `--force` | Overwrite an existing `app/Config/Iban.php`. Without it, the command refuses to touch a file that's already there and exits `1`. |

The published `app/Config/Iban.php` is namespaced `Config` and `extends \Daycry\Iban\Config\Iban`
instead of duplicating it property-by-property, so it stays forward-compatible: any property the app
doesn't override keeps inheriting the package's default (including new ones added in a later version),
and it's a real, type-checked subclass rather than a hand-copied file that can drift out of sync.
CI4's `config()`/`service('iban')` resolution prefers the app's `Config\` namespace over the package's,
so once this file exists it's returned automatically — no other wiring needed. Publishing is entirely
optional: the package works out of the box via its own bundled `Config\Iban` defaults, without ever
running this command; use it only when you want to override a property (e.g. `$provider = 'database'`)
in a file that lives in, and is tracked by, your own app.

## Bank-data importers (`iban:update`)

v1.1 introduced the bank-data importer framework (`Contracts\ImporterInterface`, `Import\ImportReport`,
`Import\ImporterRegistry`, `Import\ImportRunner`) with 5 bundled official-source importers; v1.2 grew
the bundled catalog to **30** (adding CZ/GR/SI/SK/BG/MD/PL/AZ/BE/HR/LU/MT/HU/NO/GE/IL/UA/KZ/LI/BR, plus
the EPC SEPA Register for GB/GI/IE/LV/RO) and added `iban:update --all` to run every one of them in a
single invocation — see the full workflow and the `--source=` cheat sheet in the `iban:update` entry of
[spark commands](#spark-commands) above. None of them bundle any actual data in the repository:
`iban:update` lists/selects/runs them on demand, either fetching live or importing from a local
`--file`, so the v1.0 licensing discipline (no redistributed third-party compilations — see
[`docs/licensing.md`](licensing.md)) holds unchanged.

Full reference — the `ImporterInterface` contract, every `iban:update` flag with worked examples per
source, the full bundled-importer table (country/source id/format/license/URL), the coverage matrix,
how provenance (`source_id`/`source_version`/`source_license`) is stored on each `banks` row, and how
to write and register a custom importer — lives in [`docs/importers.md`](importers.md).
