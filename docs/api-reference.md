# API Reference

This is the complete public-API reference for `daycry/iban` — every facade method, helper function, config property, DTO, enum, exception, contract, and registry member, verified against the source. For task-oriented, worked examples see [docs/usage.md](usage.md); for the bank-data importer catalog and country coverage matrix see [docs/importers.md](importers.md); for the format rendering rules see [docs/formatting.md](formatting.md).

## Contents

- [Facade — `Daycry\Iban\Iban`](#facade--daycryibaniban)
- [Helper functions (`helper('iban')`)](#helper-functions-helperiban)
- [Configuration, services & caching](#configuration-services--caching)
- [Value objects (DTOs)](#value-objects-dtos)
- [Enums & exceptions](#enums--exceptions)
- [National check-digit validators](#national-check-digit-validators)
- [Contracts & extension points](#contracts--extension-points)
- [Structural registry](#structural-registry)

## Facade — `Daycry\Iban\Iban`

[`Daycry\Iban\Iban`](src/Iban.php) is the single public entry point for the library. It composes the
three algorithmic sub-services — `Validator` → `Parser` → `Resolver` — and implements all three
framework-free contracts by delegation:
[`ValidatorInterface`](src/Contracts/ValidatorInterface.php),
[`ParserInterface`](src/Contracts/ParserInterface.php), and
[`ResolverInterface`](src/Contracts/ResolverInterface.php). The class is `final`.

### Construction

```php
public function __construct(
    Registry $registry = new Registry(),
    ProviderInterface $provider = new NullProvider(),
)
```

Both parameters default to a zero-config value ([src/Iban.php:36](src/Iban.php#L36)), so the facade is
default-constructible. The constructor wires the sub-services internally:
`Validator($registry)` → `Parser($validator)` → `Resolver($parser, $provider)`.

- `$registry` — [`Daycry\Iban\Registry\Registry`](src/Registry/Registry.php), the structural country
  registry (78 countries) used for validation and parsing.
- `$provider` — a [`ProviderInterface`](src/Contracts/ProviderInterface.php) supplying bank-data
  overlays for `resolve()`. Defaults to
  [`NullProvider`](src/Providers/NullProvider.php), which resolves no bank data (structural result
  only).

#### Standalone (zero-config)

```php
use Daycry\Iban\Iban;

$iban = new Iban(); // Registry() + NullProvider(), no framework required
```

#### CodeIgniter 4 (`service('iban')`)

```php
$iban = service('iban'); // shared instance, provider wired from Config\Iban
```

The [`Services::iban()`](src/Config/Services.php#L47) factory returns the same `Iban` facade, but
selects the provider from [`Config\Iban::$provider`](src/Config/Iban.php)
(`'null'` → `NullProvider`, `'database'` → `DatabaseProvider` over a `BankModel`, or a custom
`ProviderInterface` FQCN). When `Config\Iban::$cacheTtl` is non-`null` and the provider is not
`NullProvider`, it is wrapped in a [`CachedProvider`](src/Providers/CachedProvider.php)
([src/Config/Services.php:89](src/Config/Services.php#L89)). A bad FQCN throws
`InvalidArgumentException`.

### Methods

All signatures are verbatim from [src/Iban.php](src/Iban.php).

| Method | Signature | Returns | Throws |
| --- | --- | --- | --- |
| `validate` | `validate(string\|ParsedIban $iban, bool $checkNational = false)` | `ValidationResult` | — (never throws; violations are in the result) |
| `isValid` | `isValid(string\|ParsedIban $iban)` | `bool` | — |
| `normalize` | `normalize(string $iban)` | `string` | — |
| `parse` | `parse(string $iban)` | `ParsedIban` | `InvalidIbanException` |
| `tryParse` | `tryParse(string $iban)` | `?ParsedIban` | — (returns `null` on failure) |
| `format` | `format(string\|ParsedIban $iban, IbanFormat $f = IbanFormat::Print)` | `string` | — |
| `resolve` | `resolve(string\|ParsedIban $iban)` | `BankResult` | — |
| `validator` | `validator()` | `Validator` | — |
| `parser` | `parser()` | `Parser` | — |
| `resolver` | `resolver()` | `Resolver` | — |

#### `validate(string|ParsedIban $iban, bool $checkNational = false): ValidationResult`

Validates an IBAN and returns a [`ValidationResult`](src/DTO/ValidationResult.php); never throws.
When `$checkNational` is `true`, national check-digit validation is also applied (nine countries are
wired in v1.0 — see [National check-digit validators](#national-check-digit-validators)).

```php
$result = $iban->validate('ES9121000418450200051332', checkNational: true);
```

#### `isValid(string|ParsedIban $iban): bool`

Quick boolean check — shorthand for `validate(...)->isValid()`.

```php
$ok = $iban->isValid('ES9121000418450200051332'); // true
```

#### `normalize(string $iban): string`

Strips spaces and uppercases the input. Does not validate.

```php
$iban->normalize('es91 2100 0418 4502 0005 1332'); // 'ES9121000418450200051332'
```

#### `parse(string $iban): ParsedIban`

Parses into a [`ParsedIban`](src/DTO/ParsedIban.php); throws
[`InvalidIbanException`](src/Exceptions/InvalidIbanException.php) (which carries the failing
`ValidationResult` via `result()`) when the IBAN cannot be parsed.

```php
$parsed = $iban->parse('ES9121000418450200051332');
```

#### `tryParse(string $iban): ?ParsedIban`

Lenient parse — returns `null` instead of throwing on failure.

```php
$parsed = $iban->tryParse('not-an-iban'); // null
```

#### `format(string|ParsedIban $iban, IbanFormat $f = IbanFormat::Print): string`

Formats using an [`IbanFormat`](src/Enums/IbanFormat.php) case (`Electronic`, `Print`, `Anonymized`);
defaults to `Print`. See [docs/formatting.md](formatting.md) for the format reference.

```php
$iban->format('ES9121000418450200051332', IbanFormat::Print);
// 'ES91 2100 0418 4502 0005 1332'
```

#### `resolve(string|ParsedIban $iban): BankResult`

Resolves to a [`BankResult`](src/DTO/BankResult.php) — the `ParsedIban` plus any bank data supplied by
the configured provider (none under the default `NullProvider`).

```php
$result = $iban->resolve('ES9121000418450200051332');
```

#### Sub-service accessors

`validator()`, `parser()`, and `resolver()` return the composed
[`Validator`](src/Core/Validator.php), [`Parser`](src/Core/Parser.php), and
[`Resolver`](src/Resolver/Resolver.php) instances for direct use.

```php
$iban->validator(); // Daycry\Iban\Core\Validator
$iban->parser();    // Daycry\Iban\Core\Parser
$iban->resolver();  // Daycry\Iban\Resolver\Resolver
```

For full worked examples and the `ViolationCode` reference, see [docs/usage.md](usage.md).

## Helper functions (`helper('iban')`)

A CI4 helper defined in [src/Helpers/iban_helper.php](src/Helpers/iban_helper.php): nine procedural
wrappers around the `service('iban')` facade ([src/Iban.php](src/Iban.php)). Because the package
publishes the `Daycry\Iban\` => `src/` PSR-4 mapping, load it on demand with:

```php
helper('iban');
```

No manual `require` and no `Config\Autoload::$helpers` entry are needed (adding `'iban'` there works
too, making it always-on). Every function is guarded by `function_exists()`, so re-inclusion never
fatals. Each grabs the facade via `service('iban')` internally.

### Functions

| Function | Signature | Returns | Throws | Notes |
| --- | --- | --- | --- | --- |
| `iban_validate` | `iban_validate(string $iban, ?bool $checkNational = null)` | `ValidationResult` | never | `$checkNational = null` falls back to `Config\Iban::$checkNationalByDefault` |
| `iban_is_valid` | `iban_is_valid(string $iban, ?bool $checkNational = null)` | `bool` | never | delegates to `iban_validate()`; same config fallback |
| `iban_parse` | `iban_parse(string $iban)` | `?ParsedIban` | never | uses `tryParse()`; `null` on invalid input |
| `iban_format` | `iban_format(string $iban, ?string $format = null)` | `string` | — | `$format = null` falls back to `Config\Iban::$defaultFormat`; unknown value → `'print'` |
| `iban_resolve` | `iban_resolve(string $iban)` | `BankResult` | `InvalidIbanException` | NOT degradation-safe — mirrors `resolve()`'s contract |
| `bank_name` | `bank_name(string $iban)` | `?string` | never | `null` on invalid IBAN or unresolved entity |
| `bank_bic` | `bank_bic(string $iban)` | `?string` | never | `null` on invalid IBAN or unresolved entity |
| `iban_country` | `iban_country(string $iban)` | `?string` | never | `null` on invalid IBAN |
| `iban_valid` | `iban_valid(string $iban, ?bool $checkNational = null)` | `bool` | never | alias of `iban_is_valid()` |

Return-type imports are `Daycry\Iban\DTO\{ValidationResult, ParsedIban, BankResult}` and
`Daycry\Iban\Enums\IbanFormat`.

### Config-default fallbacks

Three functions consult [src/Config/Iban.php](src/Config/Iban.php) (via `config(Iban::class)`) when
their optional argument is `null` — the fallback lives at the helper layer, not in the facade
(the facade methods keep their own explicit defaults):

- `iban_validate()` / `iban_is_valid()` / `iban_valid()` — `$checkNational ??= config(...)->checkNationalByDefault`
  ([src/Helpers/iban_helper.php:56](src/Helpers/iban_helper.php#L56)). Default `checkNationalByDefault` is `false`.
- `iban_format()` — `$format ??= config(...)->defaultFormat`
  ([src/Helpers/iban_helper.php:114](src/Helpers/iban_helper.php#L114)). Default `defaultFormat` is `'print'`.
  The string is mapped case-insensitively: `'electronic'` → `IbanFormat::Electronic`, `'anonymized'` →
  `IbanFormat::Anonymized`, anything else → `IbanFormat::Print`.

### Degradation safety

`bank_name()`, `bank_bic()`, `iban_country()` `tryParse()` first and return `null` on failure, so they
never throw. `iban_resolve()` is the exception: it delegates straight to `resolve()` and propagates
`InvalidIbanException` for an invalid IBAN.

### Examples

```php
helper('iban');

$result  = iban_validate('DE89 3704 0044 0532 0130 00');        // ValidationResult
$ok      = iban_is_valid('DE89370400440532013000');            // true
$parsed  = iban_parse('DE89370400440532013000');               // ?ParsedIban (null if invalid)
$printed = iban_format('DE89370400440532013000');              // 'DE89 3704 ...' (Config default)
$printed = iban_format('DE89370400440532013000', 'anonymized'); // anonymized form
$bank    = iban_resolve('DE89370400440532013000');             // BankResult (throws if invalid)
$name    = bank_name('DE89370400440532013000');                // ?string
$bic     = bank_bic('DE89370400440532013000');                 // ?string
$country = iban_country('DE89370400440532013000');             // 'DE' | null
$ok      = iban_valid('DE89370400440532013000');               // alias of iban_is_valid()
```

See [docs/usage.md](usage.md) for full worked examples and the `ViolationCode` reference.

## Configuration, services & caching

The CI4 adapter exposes one publishable config class, a service factory, and a swappable
bank-data provider layer. Everything below is framework-facing (`src/Config/`,
`src/Providers/`); the framework-free core never reads any of it.

### `Config\Iban`

[src/Config/Iban.php](src/Config/Iban.php) extends `CodeIgniter\Config\BaseConfig`, so every
public property is overridable from `.env` with the `iban.` prefix (`iban.<property> = value`)
without publishing an `App\Config\Iban`.

| Property | Type | Default | `.env` key | Meaning |
| --- | --- | --- | --- | --- |
| `$provider` | `string` | `'null'` | `iban.provider` | Bank-data provider: `'null'` (no lookups, safe default), `'database'` (backed by the `banks` table via [DatabaseProvider](src/Providers/DatabaseProvider.php)), or the FQCN of a custom [ProviderInterface](src/Contracts/ProviderInterface.php). |
| `$defaultFormat` | `string` | `'print'` | `iban.defaultFormat` | Default [IbanFormat](src/Enums/IbanFormat.php) when a caller omits one: `'electronic'`, `'print'`, or `'anonymized'`. |
| `$checkNationalByDefault` | `bool` | `false` | `iban.checkNationalByDefault` | Whether [Validator](src/Core/Validator.php) runs national check-digit validation by default. |
| `$dbGroup` | `?string` | `null` | `iban.dbGroup` | `Config\Database` connection group for [DatabaseProvider](src/Providers/DatabaseProvider.php) / [BankModel](src/Models/BankModel.php). `null` = no override, so CI4's environment-aware fallback (`Config\Database::$defaultGroup`, or `'tests'` under `ENVIRONMENT === 'testing'`) applies. |
| `$table` | `string` | `'banks'` | `iban.table` | Table queried by [DatabaseProvider](src/Providers/DatabaseProvider.php) / [BankModel](src/Models/BankModel.php). |
| `$cacheTtl` | `?int` | `null` | `iban.cacheTtl` | Cache TTL in seconds for resolved bank lookups. `null` disables caching (provider left unwrapped). `0` wraps the provider in a [CachedProvider](src/Providers/CachedProvider.php) with a TTL of `0`, which CI4's cache handlers treat as **never expires** (not "disabled"). Any value `> 0` wraps it with that TTL. **BREAKING as of v1.6**: previously an `int` defaulting to `0`, and `0` meant "disabled" — if you relied on that, set `null` explicitly now. |
| `$ibanComApiKey` | `string` | `''` | `iban.ibanComApiKey` | Opt-in API key for the [iban.com Validation API](https://www.iban.com/validation-api). Empty (default) disables the fallback entirely; non-empty chains an [IbanComProvider](src/Providers/IbanComProvider.php) after the primary provider via [ChainProvider](src/Providers/ChainProvider.php). |
| `$ibanComTimeout` | `int` | `5` | `iban.ibanComTimeout` | Request timeout, in seconds, for [IbanComProvider](src/Providers/IbanComProvider.php)'s HTTP call. Only relevant when `$ibanComApiKey` is non-empty. |

### `service('iban')`

[src/Config/Services.php](src/Config/Services.php) registers the `iban` service via CI4
Composer-namespace auto-discovery — no manual wiring. `Services::iban(bool $getShared = true)`
returns the [Daycry\Iban\Iban](src/Iban.php) facade.

The non-shared branch resolves `Config\Iban` through `Factories::get('config', ...)` (honoring
a consuming app's published override) and selects the provider by matching `$provider`
([src/Config/Services.php:68](src/Config/Services.php#L68)):

```php
$provider = match ($config->provider) {
    'null'     => new NullProvider(),
    'database' => new DatabaseProvider(new BankModel($config->table, $config->dbGroup)),
    default    => self::instantiateProvider($config->provider),
};
```

For the `'database'` shortcut, `$table` / `$dbGroup` are threaded into the [BankModel](src/Models/BankModel.php)
constructor so app-level overrides are honored. The `default` arm treats `$provider` as an FQCN:
`instantiateProvider()` throws `InvalidArgumentException` when the class does not exist or does not
implement [ProviderInterface](src/Contracts/ProviderInterface.php).

The opt-in iban.com fallback is chained next, only when `Config\Iban::$ibanComApiKey` is non-empty:

```php
if ($config->ibanComApiKey !== '') {
    $provider = new ChainProvider([
        $provider,
        new IbanComProvider($config->ibanComApiKey, service('curlrequest'), $config->ibanComTimeout),
    ]);
}
```

This wraps the primary provider and an [IbanComProvider](src/Providers/IbanComProvider.php) in a
[ChainProvider](src/Providers/ChainProvider.php), in that order — the primary (local) provider is
always tried first, and [IbanComProvider](src/Providers/IbanComProvider.php) is only consulted when
it returns nothing. With the default `$ibanComApiKey = ''`, this step is a no-op and `$provider` is
left exactly as the `match` above produced it.

Caching is then applied only when opted in ([src/Config/Services.php:89](src/Config/Services.php#L89)):

```php
$cacheTtl = $config->cacheTtl;

if ($cacheTtl !== null && ! $provider instanceof NullProvider) {
    $provider = new CachedProvider($provider, service('cache'), $cacheTtl);
}
```

Note the check is `!== null`, not `> 0`: since v1.6, `null` is the "caching disabled" value and `0`
means "wrap with a TTL of `0`", which CI4's cache handlers treat as **never expires** — see
`Config\Iban::$cacheTtl`'s row above for the full BREAKING semantics. Note also the
[NullProvider](src/Providers/NullProvider.php) skip: it never resolves anything, so wrapping it would
only add a pointless cache round-trip per `resolve()`. A [ChainProvider](src/Providers/ChainProvider.php)
is never a `NullProvider`, so when the iban.com fallback is chained in, the combined local+iban.com
chain is still cached correctly whenever `$cacheTtl` is non-`null`. The facade is finally built as
`new IbanService(new Registry(), $provider)`.

> [src/Config/Registrar.php](src/Config/Registrar.php) intentionally declares no `Autoload()`
> method — it would be dead code, since `Config\Autoload` does not extend `BaseConfig` and so is
> not Registrar-merged. The bundled helper loads on demand via `helper('iban')`.

### Providers

All implement [ProviderInterface](src/Contracts/ProviderInterface.php):
`supports(string $countryCode): bool`, `findByIban(ParsedIban $iban): ?BankInfo`,
`findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo`.

| Provider | CI4-coupled | `supports()` | Behavior |
| --- | --- | --- | --- |
| [NullProvider](src/Providers/NullProvider.php) | No (framework-free default) | always `false` | Null-object: every lookup returns `null` (always unresolved). |
| [DatabaseProvider](src/Providers/DatabaseProvider.php) | Yes | always `true` | Queries the `banks` table via [BankModel::findByNaturalKey()](src/Models/BankModel.php#L86) on `(country_code, bank_code, branch_code)`, mapping the row into a [BankInfo](src/DTO/BankInfo.php) with `resolvedBy: 'database'`; `null` when unseeded. `findByIban()` delegates to `findByBankCode()`. |
| [CachedProvider](src/Providers/CachedProvider.php) | Yes | delegates to inner | Decorator over any inner provider, backed by CI4 `service('cache')`. Stores/returns the inner `BankInfo` as-is, so a cached hit preserves its `resolvedBy` unchanged. |
| [IbanComProvider](src/Providers/IbanComProvider.php) | Yes | `true` iff an API key was configured | Opt-in, paid fallback over the iban.com Validation API; sets `resolvedBy: 'iban.com'` on a successful response. `findByBankCode()` always `null` (see below). |
| [ChainProvider](src/Providers/ChainProvider.php) | No (pure composition) | `true` if ANY chained provider supports the country | Tries an ordered `list<ProviderInterface>` and returns the first non-null result, `resolvedBy` included, exactly as the winning provider set it. |

**`resolvedBy` vs `sourceId`**: `resolvedBy` identifies WHICH provider answered (`'database'`,
`'iban.com'`, or a custom provider's own id) — set by the provider itself. `sourceId` identifies the
DATASET a `DatabaseProvider` row came from (e.g. `'epc'`, `'bde'`, or `'iban.com'` when that row was
originally imported via the iban.com API), set at import/write time. The two answer different
questions and are independent: a `DatabaseProvider` row can carry `sourceId: 'iban.com'` (an
iban.com-sourced dataset previously written to the `banks` table) while `resolvedBy` still reads
`'database'` (a local DB lookup answered this particular `resolve()` call).

`DatabaseProvider::__construct(private BankModel $model = new BankModel())` defaults its model,
but `service('iban')` passes an explicitly configured one. It returns `true` from `supports()`
for every country and lets a missing row degrade to unresolved bank fields rather than skipping
the lookup.

### CachedProvider (decorator + miss sentinel)

```php
public function __construct(
    private readonly ProviderInterface $inner,
    private readonly CacheInterface $cache,
    private readonly int $ttl = 3600,
    private readonly string $prefix = 'iban_bank_',
) {}
```

`findByBankCode()` builds a sanitized cache key (`prefix + uppercased country + bank + branch`,
with any non-`[A-Za-z0-9_]` char replaced by `_`), reads the cache, and on a miss delegates to
`$inner` then stores the result. `findByIban()` delegates to this decorator's own
`findByBankCode()` so both entry points share one cache entry.

Misses are cached too, via the `private const MISS = '__iban_miss__'` sentinel: because CI4's
`CacheInterface::get()` returns `null` both for an unstored key and for a stored literal `null`,
storing a non-null sentinel lets a genuine `null` `get()` result unambiguously mean "not cached".
A cached `BankInfo` is returned as-is; any other non-null cached value is the sentinel and maps
back to `null` before returning (the sentinel never leaks to callers). Because `BankInfo` is a
`final readonly` class of plain scalars/bools, storing and re-reading it (whether from the in-process
array used in tests or a real serializing cache handler) round-trips every field — including
`resolvedBy` — unchanged: a cached hit reports the same `resolvedBy` the original lookup did.

**Warning — a `$ttl` of `0` caches misses forever too**: `$ttl` is forwarded to `CacheInterface::save()`
verbatim, and CI4's cache handlers treat `0` as "never expires" (e.g. `FileHandler::getMetaData()`:
`'expire' => $data['ttl'] > 0 ? ... : null`). Combined with the miss-caching above, a "not found" result
looked up while `$cacheTtl === 0` never expires either — after running `php spark iban:update`, run
`php spark cache:clear` or previously-missed IBANs/bank codes stay unresolved forever.

Note the constructor's own `$ttl` default of `3600` is inert in the service path — `Config\Iban::$cacheTtl`
(default `null`, meaning "disabled") always supplies the TTL when non-`null`, and the wrap only
happens in that case; once wrapped, `0` is passed through as-is (never expires), not treated as the
constructor's default.

### IbanComProvider (opt-in iban.com fallback)

```php
public function __construct(
    private readonly string $apiKey,
    ?CURLRequest $client = null,
    private readonly int $timeout = 5,
) {}
```

`$client` defaults to `service('curlrequest')` when `null`, so it's injectable for tests. Backed by the
[iban.com Validation API](https://www.iban.com/validation-api) — confirmed v4 shape:
`POST https://api.iban.com/clients/api/v4/iban/`, form-encoded, with `format=json`, `api_key`, `iban`,
and (always sent by this provider) `sci=1` to also request the SEPA Instant Credit Transfer marker.
Response JSON has `bank_data` (mapped fields: `bank` → `bankName`, `bic`, `city`, `address`),
`sepa_data` (`'YES'`/`'NO'` flags: `SCT` → `sepaSct`, `SDD` → `sepaSddCore`, `B2B` → `sepaSddB2b`,
`SCI` → `sepaSctInst`), `validations` (not consumed), and `errors` (non-empty ⇒ failure). `sourceId` is
hardcoded `'iban.com'`, `sourceVersion` is today's date (`Y-m-d`), `sourceLicense` is `'iban.com API'`,
and `resolvedBy` is likewise hardcoded `'iban.com'` (identifying the *provider*, alongside `sourceId`
identifying the *dataset* — the same value here since the dataset IS the live API response).

`findByIban()` **never throws**: the whole request + parse is wrapped in one `try`/`catch (Throwable)`,
so a DNS failure, timeout, non-200 status, malformed JSON, a non-empty `errors` array, or an empty/
missing `bank_data` all map to `null`. `supports()` returns `$this->apiKey !== ''` (key-gated, not
country-gated). `findByBankCode()` always returns `null` — the API resolves a full IBAN, not a bare
bank/branch code.

### ChainProvider (composite)

```php
/** @param list<ProviderInterface> $providers */
public function __construct(private readonly array $providers) {}
```

Framework-free (pure composition — no CI4 dependency, unlike `DatabaseProvider`/`IbanComProvider` which
also live in this same, unguarded `Providers/` directory). `supports()` is `true` if any chained
provider supports the country. `findByIban()`/`findByBankCode()` iterate `$providers` in order,
skip any that don't `supports()` the country, and return the first non-null result — used by
[Services::iban()](#serviceiban) to try the primary provider first and
[IbanComProvider](#ibancomprovider-opt-in-ibancom-fallback) only as a fallback.

### resolve() walkthrough

For an end-to-end `resolve()` example (facade → resolver → provider → `BankResult`), see
[docs/usage.md](usage.md).

## Value objects (DTOs)

Every DTO lives in [src/DTO/](src/DTO/) and is a `final readonly class` with promoted constructor
properties. They are framework-free (no `CodeIgniter\` dependency) and immutable — once constructed,
their state never changes. There are five: [`Violation`](src/DTO/Violation.php),
[`ValidationResult`](src/DTO/ValidationResult.php), [`ParsedIban`](src/DTO/ParsedIban.php),
[`BankInfo`](src/DTO/BankInfo.php), and [`BankResult`](src/DTO/BankResult.php).

### Violation

A single validation failure. Produced by the core validator and collected into a
[`ValidationResult`](src/DTO/ValidationResult.php). See [src/DTO/Violation.php](src/DTO/Violation.php).

```php
public function __construct(
    public ViolationCode $code,
    public string $messageKey,
    public string $message
)
```

| Property     | Type                                                   | Meaning                                                                                             |
| ------------ | ------------------------------------------------------ | --------------------------------------------------------------------------------------------------- |
| `code`       | [`ViolationCode`](src/Enums/ViolationCode.php) (enum)  | Machine-readable failure code (one of 8 cases, see below).                                          |
| `messageKey` | `string`                                               | Stable dot-notation key for i18n lookup (e.g. a CI4 `Language/` translation); see [docs/i18n.md](i18n.md). |
| `message`    | `string`                                               | Hardcoded English human-readable message (the core is English-only by design).                      |

No methods. The `ViolationCode` cases (backed `string` enum,
[src/Enums/ViolationCode.php](src/Enums/ViolationCode.php)): `Blank` (`'blank'`), `TooShort`
(`'too_short'`), `UnknownCountry` (`'unknown_country'`), `IllegalCharacters` (`'illegal_characters'`),
`BadLength` (`'bad_length'`), `MalformedStructure` (`'malformed_structure'`), `ChecksumFailed`
(`'checksum_failed'`), `NationalCheckFailed` (`'national_check_failed'`).

### ValidationResult

The outcome of [`Iban::validate()`](src/Iban.php). See
[src/DTO/ValidationResult.php](src/DTO/ValidationResult.php).

```php
/** @param Violation[] $violations */
public function __construct(
    public bool $valid,
    public array $violations
)
```

| Property     | Type                                    | Meaning                                                       |
| ------------ | --------------------------------------- | ------------------------------------------------------------- |
| `valid`      | `bool`                                  | `true` when the IBAN passed all checks (no violations).       |
| `violations` | `Violation[]`                           | Ordered list of failures; empty when `valid` is `true`.       |

| Method                              | Returns        | Meaning                                                         |
| ----------------------------------- | -------------- | -------------------------------------------------------------- |
| `isValid()`                         | `bool`         | Returns `$this->valid`.                                        |
| `violations()`                      | `Violation[]`  | Returns the `$violations` array.                               |
| `firstViolation()`                  | `?Violation`   | The first violation, or `null` if there are none.              |

### ParsedIban

The structural decomposition of a syntactically valid IBAN, produced by
[`Iban::parse()`](src/Iban.php) / `tryParse()`. See [src/DTO/ParsedIban.php](src/DTO/ParsedIban.php).

```php
public function __construct(
    public string $countryCode,
    public string $checkDigits,
    public string $bban,
    public string $bankIdentifier,
    public ?string $branchIdentifier,
    public string $accountNumber,
    public ?string $nationalCheckDigit,
    public bool $sepaCountry,
    public string $electronic,
)
```

| Property             | Type       | Meaning                                                                                       |
| -------------------- | ---------- | --------------------------------------------------------------------------------------------- |
| `countryCode`        | `string`   | ISO 3166-1 alpha-2 country code, e.g. `'ES'`.                                                  |
| `checkDigits`        | `string`   | The two IBAN check digits, e.g. `'91'`.                                                        |
| `bban`               | `string`   | Normalized BBAN (the country-specific part after the check digits).                           |
| `bankIdentifier`     | `string`   | Bank identifier sliced from the BBAN via the registry offsets.                                |
| `branchIdentifier`   | `?string`  | Branch identifier; `null` for countries without one (DE, NL, BE).                             |
| `accountNumber`      | `string`   | Account-number portion of the BBAN.                                                           |
| `nationalCheckDigit` | `?string`  | National check digit; structural extraction only in v1.0 (`null` where the country has none). |
| `sepaCountry`        | `bool`     | Whether the country is in SEPA, sourced from the in-code registry (useful with an empty DB).  |
| `electronic`         | `string`   | The canonical normalized (electronic) IBAN string.                                            |

| Method                                        | Returns    | Meaning                                                                                              |
| --------------------------------------------- | ---------- | --------------------------------------------------------------------------------------------------- |
| `format(IbanFormat $f = IbanFormat::Print)`   | `string`   | Delegates to [`Formatter`](src/Core/Formatter.php); default is `IbanFormat::Print`. See [docs/formatting.md](formatting.md). |
| `__toString()`                                | `string`   | Returns `$this->electronic`.                                                                        |

### BankInfo

Bank data *without* IBAN composition — the return shape of
[`ProviderInterface`](src/Contracts/ProviderInterface.php) implementations. See
[src/DTO/BankInfo.php](src/DTO/BankInfo.php). All 13 fields are nullable; a field is `null` when the
provider has no value for it.

```php
public function __construct(
    public ?string $bankName,
    public ?string $shortName,
    public ?string $bic,
    public ?string $city,
    public ?string $address,
    public ?bool $sepaSct,
    public ?bool $sepaSctInst,
    public ?bool $sepaSddCore,
    public ?bool $sepaSddB2b,
    public ?string $sourceId,
    public ?string $sourceVersion,
    public ?string $sourceLicense,
    public ?string $resolvedBy = null,
)
```

| Property        | Type       | Meaning                                                       |
| --------------- | ---------- | ------------------------------------------------------------- |
| `bankName`      | `?string`  | Full bank name.                                               |
| `shortName`     | `?string`  | Abbreviated / display name.                                   |
| `bic`           | `?string`  | Bank Identifier Code (SWIFT/BIC).                             |
| `city`          | `?string`  | Bank city.                                                    |
| `address`       | `?string`  | Bank address.                                                 |
| `sepaSct`       | `?bool`    | Supports SEPA Credit Transfer.                                |
| `sepaSctInst`   | `?bool`    | Supports SEPA Instant Credit Transfer.                        |
| `sepaSddCore`   | `?bool`    | Supports SEPA Direct Debit Core.                              |
| `sepaSddB2b`    | `?bool`    | Supports SEPA Direct Debit B2B.                               |
| `sourceId`      | `?string`  | Identifier of the DATASET the record came from (e.g. `'epc'`, `'bde'`, `'iban.com'`), `null` for a hand-seeded row. |
| `sourceVersion` | `?string`  | Version/snapshot of the source data.                         |
| `sourceLicense` | `?string`  | License under which the source data is distributed.          |
| `resolvedBy`    | `?string`  | Identifies WHICH provider produced this data (`'database'`, `'iban.com'`, or a custom provider's own id) — distinct from `sourceId` above, which identifies the *dataset*. `null` when unknown. Defaults to `null`. |

No methods.

### BankResult

The output of [`Resolver::resolve()`](src/Resolver/Resolver.php) (surfaced via
[`Iban::resolve()`](src/Iban.php)): a [`ParsedIban`](src/DTO/ParsedIban.php) composed with the same 13
nullable bank fields as [`BankInfo`](src/DTO/BankInfo.php). See
[src/DTO/BankResult.php](src/DTO/BankResult.php).

```php
public function __construct(
    public ParsedIban $iban,
    public ?string $bankName,
    public ?string $shortName,
    public ?string $bic,
    public ?string $city,
    public ?string $address,
    public ?bool $sepaSct,
    public ?bool $sepaSctInst,
    public ?bool $sepaSddCore,
    public ?bool $sepaSddB2b,
    public ?string $sourceId,
    public ?string $sourceVersion,
    public ?string $sourceLicense,
    public ?string $resolvedBy = null,
)
```

| Property                          | Type          | Meaning                                                                     |
| --------------------------------- | ------------- | --------------------------------------------------------------------------- |
| `iban`                            | `ParsedIban`  | The parsed IBAN this result was resolved from (never `null`).               |
| `bankName` … `resolvedBy` (13)    | see `BankInfo` | Identical to the 13 nullable fields of [`BankInfo`](src/DTO/BankInfo.php) above; `null` when unresolved/unknown. |

| Method          | Returns  | Meaning                                                                                          |
| --------------- | -------- | ------------------------------------------------------------------------------------------------ |
| `isResolved()`  | `bool`   | `true` when *any* of the 12 bank-data fields is non-null; `false` when the provider supplied no data. Deliberately excludes `resolvedBy` (provenance metadata, not bank data) — a result with only `resolvedBy` set still reports `false`. |

With the default [`NullProvider`](src/Providers/NullProvider.php), `resolve()` always returns a
`BankResult` whose 12 bank fields are `null` and whose `isResolved()` is therefore `false`.

## Enums & exceptions

The core exposes two enums and two exception types. Both enums are defined verbatim from the design spec (§4). See [src/Enums/ViolationCode.php](src/Enums/ViolationCode.php), [src/Enums/IbanFormat.php](src/Enums/IbanFormat.php), [src/Exceptions/IbanException.php](src/Exceptions/IbanException.php), and [src/Exceptions/InvalidIbanException.php](src/Exceptions/InvalidIbanException.php).

### ViolationCode

A backed `string` enum ([src/Enums/ViolationCode.php](src/Enums/ViolationCode.php)) with 8 cases. Each `Violation` carries one case; the backing value is the stable external identifier (safe to surface in APIs, logs, and translation keys). The [Validator](src/Core/Validator.php) runs a fixed check pipeline that short-circuits on the **first** failure, so at most one violation is ever returned. The cases below are listed in that pipeline order ([src/Core/Validator.php:85](src/Core/Validator.php#L85)).

| Case | Backing value | Emitted when |
| --- | --- | --- |
| `Blank` | `blank` | The normalized input is the empty string ([src/Core/Validator.php:98](src/Core/Validator.php#L98)). |
| `TooShort` | `too_short` | The normalized input is shorter than 4 characters — not even a country code plus check digits ([src/Core/Validator.php:106](src/Core/Validator.php#L106)). |
| `UnknownCountry` | `unknown_country` | The 2-letter country code is not present in the [Registry](src/Registry/Registry.php) ([src/Core/Validator.php:124](src/Core/Validator.php#L124)). |
| `IllegalCharacters` | `illegal_characters` | After normalization the string still contains a character outside `[A-Z0-9]` ([src/Core/Validator.php:114](src/Core/Validator.php#L114)). |
| `BadLength` | `bad_length` | The length does not match the country's registered `ibanLength` ([src/Core/Validator.php:134](src/Core/Validator.php#L134)). Also emitted pre-normalization as a fail-fast guard when raw input exceeds 64 characters ([src/Core/Validator.php:88](src/Core/Validator.php#L88)). |
| `MalformedStructure` | `malformed_structure` | The check digits are not both numeric, or the BBAN does not match the country's compiled structure pattern ([src/Core/Validator.php:145](src/Core/Validator.php#L145)). |
| `ChecksumFailed` | `checksum_failed` | The ISO 7064 MOD-97-10 checksum over the rearranged IBAN is not 1 ([src/Core/Validator.php:153](src/Core/Validator.php#L153)). |
| `NationalCheckFailed` | `national_check_failed` | Only when `validate(..., checkNational: true)` is passed **and** a national validator is registered for the country, and that validator rejects the national check digit ([src/Core/Validator.php:161](src/Core/Validator.php#L161)). Otherwise this step is a silent skip, never a failure. |

Note that the enum declaration orders `UnknownCountry` before `IllegalCharacters`, but the Validator applies `IllegalCharacters` first (illegal characters are checked before the country code is looked up). The table above reflects the runtime pipeline order.

### IbanFormat

A pure enum ([src/Enums/IbanFormat.php](src/Enums/IbanFormat.php)) selecting the rendering style used by `Iban::format()` and the [Formatter](src/Core/Formatter.php). `IbanFormat::Print` is the facade default.

| Case | Description |
| --- | --- |
| `Electronic` | Contiguous, uppercase, no separators (e.g. `ES9121000418450200051332`). |
| `Print` | Space-grouped in blocks of four for human readability (e.g. `ES91 2100 0418 4502 0005 1332`). |
| `Anonymized` | Print grouping with the interior masked, exposing only enough to identify the country/head and tail. |

See [docs/formatting.md](formatting.md) for the exact rendering rules and worked examples of each format.

### Exceptions

Both live in [src/Exceptions](src/Exceptions).

#### IbanException

```php
class IbanException extends \RuntimeException
```

The base exception type for the library ([src/Exceptions/IbanException.php](src/Exceptions/IbanException.php)). It extends `\RuntimeException` and is not thrown directly anywhere in v1.0; it exists so callers can `catch (IbanException $e)` to trap any library-originated failure regardless of subtype.

#### InvalidIbanException

```php
final class InvalidIbanException extends IbanException
{
    public function __construct(
        private readonly ValidationResult $resultValue,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    );

    public function result(): ValidationResult;
}
```

`final`, extends `IbanException` ([src/Exceptions/InvalidIbanException.php](src/Exceptions/InvalidIbanException.php)). Thrown by the strict parse path — `Iban::parse()` via the [Parser](src/Core/Parser.php#L43) — when validation fails. It carries the failing [ValidationResult](src/DTO/ValidationResult.php), retrievable via `result()`, so a caller can inspect the exact `Violation` (and its `ViolationCode`) that caused the rejection.

When no explicit `$message` is passed, the constructor derives one from the result's first violation message, falling back to `'Invalid IBAN'` when the result carries no violations. The non-throwing entry points — `validate()`, `isValid()`, and `tryParse()` — never raise this exception; they report failure through the returned `ValidationResult` (or `null`) instead.

## National check-digit validators

Beyond the country-agnostic MOD-97 checksum, many countries embed a second,
domestic check digit (or letter) inside the BBAN. The core ships optional
per-country validators for these. They are **opt-in**: a national check never
runs unless you ask for it, and even then a missing validator for the country
is a silent skip, never a failure.

### How national checks work

National validation is the final, eighth step of the check pipeline in
[src/Core/Validator.php:161](src/Core/Validator.php#L161). It runs only when
**both** conditions hold: `$checkNational` is `true` **and** a validator is
registered for the IBAN's country code. All prior steps (structure, length,
MOD-97 checksum, …) must already have passed.

```php
public function validate(string|ParsedIban $iban, bool $checkNational = false): ValidationResult
```

Enable it per call by passing `true`, exposed identically on the facade
([src/Iban.php:46](src/Iban.php#L46)) and the `ValidatorInterface`
([src/Contracts/ValidatorInterface.php:27](src/Contracts/ValidatorInterface.php#L27)):

```php
$iban->validate('ES9121000418450200051332', checkNational: true);
```

A failed national check yields a single `ViolationCode::NationalCheckFailed`
violation (message `iban.violation.national_check_failed`,
"The national check digits are invalid."). Like every other step, it is
reported through the returned `ValidationResult` — `validate()`/`isValid()`
never throw.

Application-wide default: `Config\Iban::$checkNationalByDefault`
([src/Config/Iban.php:42](src/Config/Iban.php#L42)), which is `false` out of the
box. This config value is **not** consumed by the core `Validator` (which
defaults `$checkNational` to `false`); it is applied by the CI4 adapters — the
`iban_validate()` / `iban_is_valid()` / `iban_valid()` helpers
([src/Helpers/iban_helper.php:56](src/Helpers/iban_helper.php#L56)) and the
`iban:validate` command
([src/Commands/ValidateCommand.php:48](src/Commands/ValidateCommand.php#L48)),
each of which falls back to the config value when no explicit flag is supplied.

### Supported countries

The default map lives in the `Validator` constructor
([src/Core/Validator.php:71](src/Core/Validator.php#L71)), keyed by upper-case
country code. Nine countries are wired in across seven validator classes — MC
reuses the French RIB validator and SM reuses the Italian CIN validator, since
each shares the other's BBAN structure and algorithm.

| Country | Validator class | Algorithm family |
|---|---|---|
| ES | [SpanishNationalCheckValidator](src/National/SpanishNationalCheckValidator.php) | Weighted mod-11, two control digits (DC1 over bank+branch, DC2 over account) |
| BE | [BelgianNationalCheckValidator](src/National/BelgianNationalCheckValidator.php) | First 10 BBAN digits mod 97 (remainder 0 → 97), 2-digit check |
| PT | [PortugueseNationalCheckValidator](src/National/PortugueseNationalCheckValidator.php) | Weighted NIB mod 97 (NIB ≡ 1 mod 97), 2-digit check |
| SI | [SlovenianNationalCheckValidator](src/National/SlovenianNationalCheckValidator.php) | ISO 7064 MOD 97-10 over 13 digits (98 − (n·100 mod 97)), 2-digit check |
| FI | [FinnishNationalCheckValidator](src/National/FinnishNationalCheckValidator.php) | Luhn (mod-10) over the preceding 13 digits, final BBAN digit |
| FR | [FrenchNationalCheckValidator](src/National/FrenchNationalCheckValidator.php) | RIB key: 97 − ((89·bank + 15·branch + 3·account) mod 97), 2-digit key |
| MC | [FrenchNationalCheckValidator](src/National/FrenchNationalCheckValidator.php) | Same RIB key algorithm as FR (shared structure) |
| IT | [ItalianNationalCheckValidator](src/National/ItalianNationalCheckValidator.php) | CIN: odd/even position-weighted sum mod 26 → check letter |
| SM | [ItalianNationalCheckValidator](src/National/ItalianNationalCheckValidator.php) | Same CIN algorithm as IT (shared structure) |

Each validator's class docblock documents the exact formula and records the
real registry example IBAN(s) it was verified against.

Note that all mod-97 based validators delegate reduction to
`Mod97::mod97()` (a windowed, 32-bit-safe reducer) rather than casting long
digit strings to `int`, and the FR/MC RIB validator uses a streaming
per-component remainder for the same reason.

#### Estonia (EE) is intentionally not shipped

Estonia is deliberately excluded. Its real check-digit algorithm depends on a
bank-specific, variable-length raw domestic account number that cannot be
reconstructed from the fixed-width IBAN fields alone, so a generic
implementation would reject genuine, valid EE IBANs. See the rationale in
[src/Core/Validator.php:63](src/Core/Validator.php#L63).

### Adding your own validator

Implement `NationalCheckValidatorInterface`
([src/Contracts/NationalCheckValidatorInterface.php](src/Contracts/NationalCheckValidatorInterface.php)),
which has two methods:

```php
public function supports(string $countryCode): bool;   // ISO 3166-1 alpha-2
public function verify(ParsedIban $iban): bool;         // true = valid / skip
```

By convention each shipped validator guards `verify()` with its own
`supports()` and returns `true` (a skip, not a failure) when the country does
not apply — the `Validator` already filters by country code before calling,
but the guard keeps a validator safe to call directly. It returns `false` only
when the check genuinely fails.

Register your instances by passing a `country => instance` map as the
`$nationalValidators` constructor argument of `Validator`
([src/Core/Validator.php:71](src/Core/Validator.php#L71)) — the map you supply
**replaces** the built-in defaults, so re-list any built-ins you still want:

```php
use Daycry\Iban\Core\Validator;
use Daycry\Iban\Registry\Registry;

$validator = new Validator(
    new Registry(),
    nationalValidators: [
        'ES' => new SpanishNationalCheckValidator(),
        'NL' => new MyDutchNationalCheckValidator(),
    ],
);

$validator->validate('NL91ABNA0417164300', checkNational: true);
```

Note that the `Daycry\Iban\Iban` facade
([src/Iban.php:36](src/Iban.php#L36)) constructs its `Validator` internally as
`new Validator($registry)` and does **not** expose the `$nationalValidators`
map, so a custom map requires constructing a `Validator` directly (and, if you
want the full facade API over it, wiring your own `Parser`/`Resolver`, or using
the `Validator` on its own).

See [docs/usage.md](usage.md) for the full validation API and the complete
`ViolationCode` table.

## Contracts & extension points

Every seam in `daycry/iban` is expressed as a small, framework-free interface in
[src/Contracts](src/Contracts). The seven interfaces below carry no `CodeIgniter\` dependency
(enforced by [tests/Architecture/CoreIsFrameworkFreeTest.php](tests/Architecture/CoreIsFrameworkFreeTest.php)),
so anything written purely against them stays usable standalone. Implement one to swap in your own
behavior; wire it through [Config\Iban](src/Config/Iban.php) or the relevant registry.

### The seven interfaces

| Interface | File | Role |
|-----------|------|------|
| `ValidatorInterface` | [src/Contracts/ValidatorInterface.php](src/Contracts/ValidatorInterface.php) | Validate an IBAN, never throwing — violations are returned in the `ValidationResult`. |
| `ParserInterface` | [src/Contracts/ParserInterface.php](src/Contracts/ParserInterface.php) | Normalize / parse / try-parse / format an IBAN. |
| `ProviderInterface` | [src/Contracts/ProviderInterface.php](src/Contracts/ProviderInterface.php) | Supply bank data (name, BIC, SEPA flags…) for a given IBAN or bank code. |
| `ResolverInterface` | [src/Contracts/ResolverInterface.php](src/Contracts/ResolverInterface.php) | Compose a `BankResult` from a parsed IBAN plus a provider overlay. |
| `RegistryLoaderInterface` | [src/Contracts/RegistryLoaderInterface.php](src/Contracts/RegistryLoaderInterface.php) | Load the raw structural country registry. |
| `NationalCheckValidatorInterface` | [src/Contracts/NationalCheckValidatorInterface.php](src/Contracts/NationalCheckValidatorInterface.php) | Verify country-specific national check digits. |
| `ImporterInterface` | [src/Contracts/ImporterInterface.php](src/Contracts/ImporterInterface.php) | Yield normalized `banks` rows for one `(country, source)` pair. |

#### `ValidatorInterface`

```php
public function validate(string|ParsedIban $iban, bool $checkNational = false): ValidationResult;
public function isValid(string|ParsedIban $iban): bool;
```

`validate()` never throws; violations are collected into the returned
[ValidationResult](src/DTO/ValidationResult.php). `$checkNational` additionally runs the national
check-digit validator when one exists for the country.

#### `ParserInterface`

```php
public function normalize(string $iban): string;
public function parse(string $iban): ParsedIban;              // throws InvalidIbanException
public function tryParse(string $iban): ?ParsedIban;          // null on failure
public function format(string|ParsedIban $iban, IbanFormat $f = IbanFormat::Print): string;
```

`parse()` throws [InvalidIbanException](src/Exceptions/InvalidIbanException.php) on unparseable
input; `tryParse()` returns `null` instead. `$f` defaults to `IbanFormat::Print` (see
[src/Enums/IbanFormat.php](src/Enums/IbanFormat.php)).

#### `ProviderInterface`

```php
public function supports(string $countryCode): bool;
public function findByIban(ParsedIban $iban): ?BankInfo;
public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo;
```

`$countryCode` is an ISO 3166-1 alpha-2 code. Both finders return `null` when nothing matches. This
is the primary extension point for custom bank-data sources — see the recipe below.

#### `ResolverInterface`

```php
public function resolve(string|ParsedIban $iban): BankResult;
```

The bundled [Resolver](src/Resolver/Resolver.php) implements the precedence rule: when the provider
`supports()` the IBAN's country, it tries `findByIban()` first, then falls back to
`findByBankCode()` with `$branchCode` passed as `null` (a deliberate bank-level fallback, see
[src/Resolver/Resolver.php:47](src/Resolver/Resolver.php#L47)). A `string` argument is parsed via
`Parser::parse()` (throwing on invalid input); a `ParsedIban` is used as-is.

#### `RegistryLoaderInterface`

```php
public function load(): array;   // @return array<string, mixed> keyed by country code
```

Implemented by [PhpRegistryLoader](src/Registry/PhpRegistryLoader.php), which reads
[src/Registry/data/countries.php](src/Registry/data/countries.php).

#### `NationalCheckValidatorInterface`

```php
public function supports(string $countryCode): bool;
public function verify(ParsedIban $iban): bool;
```

Absence of an implementation for a country is treated as a *skip*, never a failure. The v1.0
implementations are listed under [National check-digit validators](#national-check-digit-validators)
(nine countries across seven classes, e.g. [SpanishNationalCheckValidator](src/National/SpanishNationalCheckValidator.php)).

#### `ImporterInterface`

```php
public function countryCode(): string;                       // ISO 3166-1 alpha-2, e.g. 'AT'
public function sourceId(): string;                          // stable machine id, e.g. 'oenb'
public function sourceName(): string;                        // human-readable publisher name
public function license(): string;                           // attribution recorded per row
public function sourceUrl(): string;                         // official download URL
public function rows(?string $localFile = null): iterable;   // @return iterable<array<string, mixed>>
```

`(countryCode(), sourceId())` is the natural key an importer is registered under. `rows()` yields
normalized bank rows; when `$localFile` is given the importer MUST parse that file offline instead of
fetching from `sourceUrl()`. Recognized row keys: `bank_code` (string, **required**), plus optional
`branch_code`, `bic`, `name`, `short_name`, `city`, `address`, and the four boolean SEPA flags
`sepa_sct`, `sepa_sct_inst`, `sepa_sdd_core`, `sepa_sdd_b2b`. The provenance columns
(`country_code`, `source_id`, `source_license`, `source_version`, `updated_at`) are added by
[ImportRunner](src/Import/ImportRunner.php), not the importer. Because the whole `Contracts`
directory is framework-free, concrete importers must fetch over the network with plain PHP
(`file_get_contents()`, streams, curl), never a framework HTTP client.

### Recipe (a): custom `ProviderInterface`

To back resolution with an external API (or any store other than the bundled `banks` table),
implement `ProviderInterface` and point `Config\Iban::$provider` at its FQCN.

```php
<?php

declare(strict_types=1);

namespace App\Iban;

use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ParsedIban;

final class ApiBankProvider implements ProviderInterface
{
    public function supports(string $countryCode): bool
    {
        return $countryCode === 'ES'; // only claim the countries you can serve
    }

    public function findByIban(ParsedIban $iban): ?BankInfo
    {
        return $this->findByBankCode($iban->countryCode, $iban->bankIdentifier, $iban->branchIdentifier);
    }

    public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
    {
        // ... call your API, then map the response into a BankInfo, or return null on a miss.
        return new BankInfo(bankName: 'Example Bank', bic: 'EXAMPLEXXX' /* ... */);
    }
}
```

Wire it through configuration (in a CI4 app, `app/Config/Iban.php` or the `iban.provider` env key):

```php
public string $provider = \App\Iban\ApiBankProvider::class;
```

[Config\Services::iban()](src/Config/Services.php) resolves this FQCN via `instantiateProvider()`:
the class must exist and implement `ProviderInterface` (otherwise an `InvalidArgumentException` is
thrown), and it is instantiated with **no constructor arguments** (`new $fqcn()`) — give your
provider a zero-arg constructor, or resolve its dependencies internally. If
`Config\Iban::$cacheTtl` is non-`null`, the service transparently wraps your provider in
[CachedProvider](src/Providers/CachedProvider.php). Standalone (no CI4) you can instead hand your
provider straight to `new Resolver($parser, $provider)`. Return `null` from the finders for a miss;
the resolver still produces a `BankResult` carrying the parsed IBAN with empty bank fields.

### Recipe (b): custom `ImporterInterface`

Implement `ImporterInterface` for a new `(country, source)`, then register the instance with an
[ImporterRegistry](src/Import/ImporterRegistry.php):

```php
$registry = new ImporterRegistry();          // ctor loads the 30 bundled defaults
$registry->register(new MyCentralBankImporter());  // add or replace by (country, source) key
```

`register()` keys on `strtoupper(country) . ':' . strtolower(sourceId)`, so registering the same pair
again **replaces** the earlier importer. Query the registry with `all()`, `forCountry(string)`,
`get(string $countryCode, string $sourceId)`, or `sources()` (a display summary). Run one importer
with `ImportRunner::run()`:

```php
public function run(
    ImporterInterface $importer,
    BankModel $model,
    bool $dryRun = false,
    ?string $localFile = null,
): ImportReport;
```

It fetches `rows()`, skips any row missing a non-empty `bank_code`, stamps one `updated_at` /
`source_version` per run, and upserts each row by natural key `(country_code, bank_code, branch_code)`
via [BankModel](src/Models/BankModel.php). The returned
[ImportReport](src/Import/ImportReport.php) is a `final readonly` DTO:

```php
public function __construct(
    public string $countryCode,
    public string $sourceId,
    public int $fetched,
    public int $imported,
    public int $skipped,
    public bool $dryRun,
    public array $messages = [],   // string[]
) {}
```

For the full step-by-step (parsing sources, provenance, offline `--file` imports), see
[docs/importers.md](importers.md) → "Writing a custom importer".

## Structural registry

The structural registry is the framework-free source of truth for each country's IBAN format:
its fixed length, BBAN token string, SEPA membership, an example IBAN, and the positional offsets
of the bank / branch / account / national-check fields. The core validator, parser and formatter
all read structure from it; nothing else in the package hard-codes per-country IBAN layout.

It covers **78 countries** and is **independently authored** from publicly documented IBAN
formats — not copied from, nor derived from, the SWIFT IBAN Registry file. See
[docs/registry-authoring.md](registry-authoring.md) for the authoring/cross-check
methodology and [docs/licensing.md](licensing.md) for the data-licensing rationale.
For the per-country coverage listing, see the coverage matrix in
[docs/importers.md](importers.md#coverage-matrix) rather than enumerating countries here.

### Registry API

[`Daycry\Iban\Registry\Registry`](src/Registry/Registry.php) is a `final` class. It takes a
[`RegistryLoaderInterface`](src/Contracts/RegistryLoaderInterface.php) (defaulting to
[`PhpRegistryLoader`](src/Registry/PhpRegistryLoader.php)), hydrates the raw data into
[`CountryStructure`](src/Registry/CountryStructure.php) DTOs on first access, and caches the
hydrated map so repeated lookups don't re-hydrate.

```php
public function __construct(RegistryLoaderInterface $loader = new PhpRegistryLoader());
public function has(string $cc): bool;
public function get(string $cc): CountryStructure;      // throws OutOfBoundsException
public function all(): array;                            // array<string, CountryStructure>
```

| Member | Signature | Notes |
| --- | --- | --- |
| `has()` | `has(string $cc): bool` | Case-insensitive — `$cc` is uppercased before lookup. |
| `get()` | `get(string $cc): CountryStructure` | Case-insensitive. Throws `\OutOfBoundsException` when no structure is registered for `$cc`. |
| `all()` | `all(): array<string, CountryStructure>` | Full hydrated map, keyed by uppercase ISO 3166-1 alpha-2 code. |
| `VERSION` | `public const string VERSION` | See below. |

Lookups are case-insensitive: both [`has()`](src/Registry/Registry.php#L49) and
[`get()`](src/Registry/Registry.php#L61) normalize `$cc` with `strtoupper()`, so `'es'`,
`'Es'` and `'ES'` resolve identically. `get()` on an unknown code throws
[`\OutOfBoundsException`](src/Registry/Registry.php#L67).

#### `VERSION` const and its provenance

```php
public const string VERSION = '2026-07 (78 countries; independently authored, not derived from the SWIFT IBAN Registry file)';
```

`VERSION` ([src/Registry/Registry.php:30](src/Registry/Registry.php#L30)) is a typed class
constant that doubles as a provenance marker. Its wording is deliberate: it records the refresh
period (`2026-07`), the country count, and — importantly — asserts that the structural facts are
independently authored rather than lifted from the SWIFT IBAN Registry file. Treat it as the
registry's version stamp for the ~annual refresh cycle.

### CountryStructure

[`Daycry\Iban\Registry\CountryStructure`](src/Registry/CountryStructure.php) is a
`final readonly` DTO with promoted public constructor properties. Offset/length fields are
`[offset, length]` integer pairs (0-indexed into the normalized, electronic-format IBAN);
`branch` and `nationalCheck` are `null` for countries whose BBAN has no such field.

| Property | Type | Meaning |
| --- | --- | --- |
| `countryCode` | `string` | Two-letter ISO 3166-1 alpha-2 code, uppercase (e.g. `'ES'`, `'GB'`). |
| `ibanLength` | `int` | Fixed total IBAN length for this country. |
| `bbanStructure` | `string` | SWIFT format tokens for the BBAN (e.g. `'4!n4!n2!n10!n'` for Spain). |
| `bank` | `array{0:int,1:int}` | Offset and length of the bank field. |
| `branch` | `array{0:int,1:int}\|null` | Offset and length of the branch field, or `null` if unused. |
| `account` | `array{0:int,1:int}` | Offset and length of the account-number field. |
| `nationalCheck` | `array{0:int,1:int}\|null` | Offset and length of the national check digit(s), or `null` if unused. |
| `sepa` | `bool` | Whether the country is in the SEPA region (EPC409-09). |
| `ibanExampleElectronic` | `string` | Example IBAN in electronic (no-spaces) format. |

### Loading and regeneration

[`PhpRegistryLoader`](src/Registry/PhpRegistryLoader.php) is the default
`RegistryLoaderInterface` implementation. Its `load()` returns the raw array by `require`-ing the
bundled [src/Registry/data/countries.php](src/Registry/data/countries.php), resolved
relative to the loader (via `__DIR__`) so it works regardless of the caller's working directory.
Because `Registry` accepts any loader, consumers can substitute an alternative source (e.g. a
compiled/cached registry) without modifying `Registry` itself.

`src/Registry/data/countries.php` is a **generated file** — do not hand-edit it. It is regenerated
by [bin/generate-registry.php](bin/generate-registry.php) from the independently authored
fact source [bin/data/registry-facts.csv](bin/data/registry-facts.csv):

```bash
php bin/generate-registry.php            # rewrite src/Registry/data/countries.php
php bin/generate-registry.php --dry-run   # print a diff, write nothing; exit 1 on drift
```

The generator validates each CSV row strictly (country-code shape, integer offsets/lengths within
`[4, iban_length]`, `sepa` exactly `0`/`1`, and the example IBAN's prefix/length matching the row),
and emits canonical, deterministic PHP (entries sorted by country code, LF-only) so the same CSV
always yields byte-identical output — the property the drift test relies on. See
[docs/registry-authoring.md](registry-authoring.md) for the annual-refresh workflow.
