<?php

declare(strict_types=1);

namespace Daycry\Iban\Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Publishable configuration for the `daycry/iban` package.
 *
 * Every property is overridable via `.env` using the `iban.<property>`
 * prefix (e.g. `iban.provider = database`), courtesy of
 * {@see BaseConfig}'s environment-variable resolution.
 *
 * @see \Daycry\Iban\Config\Services::iban()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
class Iban extends BaseConfig
{
    /**
     * Bank-data resolution provider.
     *
     * One of `'null'` (no lookups, the safe out-of-the-box default),
     * `'database'` (backed by the `banks` table via
     * {@see \Daycry\Iban\Providers\DatabaseProvider}), or the fully
     * qualified class name of a custom
     * {@see \Daycry\Iban\Contracts\ProviderInterface} implementation.
     */
    public string $provider = 'null';

    /**
     * Default {@see \Daycry\Iban\Enums\IbanFormat} used when callers don't
     * explicitly request one: `'electronic'`, `'print'`, or `'anonymized'`.
     */
    public string $defaultFormat = 'print';

    /**
     * Whether {@see \Daycry\Iban\Core\Validator} runs the national
     * check-digit validation by default.
     */
    public bool $checkNationalByDefault = false;

    /**
     * The `Config\Database` connection group queried by
     * {@see \Daycry\Iban\Providers\DatabaseProvider} / {@see \Daycry\Iban\Models\BankModel}.
     *
     * Defaults to `null`, meaning "no override": {@see BankModel} then leaves
     * its own `$DBGroup` unset, so CI4's environment-aware fallback
     * (`Database\Config::connect(null)`, which resolves to `'tests'` under
     * `ENVIRONMENT === 'testing'`, or the app's `Config\Database::$defaultGroup`
     * otherwise) applies transparently. Set this only to force a specific
     * connection group regardless of environment (e.g. a read replica).
     */
    public ?string $dbGroup = null;

    /**
     * The table name queried by
     * {@see \Daycry\Iban\Providers\DatabaseProvider} / {@see \Daycry\Iban\Models\BankModel}.
     */
    public string $table = 'banks';

    /**
     * Cache TTL, in seconds, for resolved bank lookups. Nullable since v2.0
     * -- BREAKING: this used to be an `int` defaulting to `0` meaning
     * "caching disabled". That collided with CI4's own cache-handler
     * convention, where a TTL of `0` means "never expires" (see
     * `FileHandler::getMetaData()` / `RedisHandler::save()`), and left no
     * way to actually configure a never-expiring cache. The semantics are
     * now:
     *
     * - `null` (the default) -- caching is disabled: {@see \Daycry\Iban\Config\Services::iban()}
     *   leaves the resolver's provider unwrapped, so behavior is identical
     *   to pre-cache versions of this package (and to the old `0` default).
     * - `0` -- the provider IS wrapped in a
     *   {@see \Daycry\Iban\Providers\CachedProvider}, and `0` is passed
     *   straight through to CI4's `service('cache')`, which treats it as
     *   "never expires" (CI4 semantics, not this package's).
     * - Any value `> 0` -- wraps the provider with this TTL in seconds.
     *
     * **Migration from < v2.0**: if you previously set `$cacheTtl = 0` to
     * DISABLE caching, change it to `null` -- `0` now means the opposite
    * (never expires). Only successful bank resolutions are cached; provider
    * misses (`null`) are never written, regardless of the configured TTL.
     */
    public ?int $cacheTtl = null;

    /**
     * Opt-in API key for the iban.com Validation API
     * (`https://api.iban.com/clients/api/v4/iban/`), used as a last-resort
     * bank-data fallback.
     *
     * Empty string (the default) disables the fallback entirely — behavior
     * is identical to a package with no knowledge of iban.com. When
     * non-empty, {@see \Daycry\Iban\Config\Services::iban()} chains a
     * {@see \Daycry\Iban\Providers\IbanComProvider} AFTER the primary
     * provider (via {@see \Daycry\Iban\Providers\ChainProvider}), so
     * iban.com is only queried once the primary provider (e.g. the local
     * `banks` table) has already failed to resolve the IBAN. Set it via
     * `.env` (`iban.ibanComApiKey = <key>`) — never commit a real key to the
     * repository.
     *
     * Note this is a **paid, external** API: every fallback lookup sends the
     * full IBAN to iban.com over the network. See
     * [docs/usage.md](../../docs/usage.md) for the privacy/cost caveat.
     */
    public string $ibanComApiKey = '';

    /**
     * Request timeout, in seconds, for {@see \Daycry\Iban\Providers\IbanComProvider}'s
     * HTTP call to the iban.com Validation API. Only relevant when
     * {@see self::$ibanComApiKey} is non-empty.
     */
    public int $ibanComTimeout = 5;

    /**
     * Source for the ISO 3166-1 country registry
     * ({@see \Daycry\Iban\Registry\IsoCountryRegistry}), used by BIC
     * validation to recognise a BIC's country code.
     *
     * - `'php'` (the default) — the bundled compiled list
     *   ({@see \Daycry\Iban\Registry\PhpIsoCountryLoader}). Zero dependencies:
     *   no database, no configuration, works standalone.
     * - `'database'` — read the `iso_countries` table via
     *   {@see \Daycry\Iban\Providers\DatabaseIsoCountryLoader}. This requires
     *   the `CreateIsoCountriesTable` migration to have run AND the table to
     *   be populated (e.g. `php spark db:seed
     *   "Daycry\Iban\Database\Seeds\IsoCountriesSeeder"`); an empty/missing
     *   table yields an empty registry. Only useful if you want to curate the
     *   country set in the database instead of using the compiled list.
     *
     * Any value other than `'database'` is treated as `'php'`.
     */
    public string $isoCountrySource = 'php';

    /**
     * The table name queried when {@see self::$isoCountrySource} is
     * `'database'`, via {@see \Daycry\Iban\Providers\DatabaseIsoCountryLoader}
     * / {@see \Daycry\Iban\Models\IsoCountryModel}.
     */
    public string $isoCountryTable = 'iso_countries';
}
