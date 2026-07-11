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
     * Cache TTL, in seconds, for resolved bank lookups.
     *
     * `0` (the default) disables caching entirely: {@see \Daycry\Iban\Config\Services::iban()}
     * leaves the resolver's provider unwrapped, so behavior is identical to
     * pre-cache versions of this package. Any value `> 0` wraps the
     * provider in a {@see \Daycry\Iban\Providers\CachedProvider} (backed by
     * CI4's `service('cache')`) with this TTL, so repeated identical
     * `findByBankCode()`/`findByIban()` lookups -- including misses -- are
     * served from cache instead of re-querying the underlying provider.
     */
    public int $cacheTtl = 0;

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
}
