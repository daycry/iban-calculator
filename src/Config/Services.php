<?php

declare(strict_types=1);

namespace Daycry\Iban\Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Config\Factories;
use Daycry\Iban\Config\Iban as IbanConfig;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\Iban as IbanService;
use Daycry\Iban\Models\BankModel;
use Daycry\Iban\Providers\CachedProvider;
use Daycry\Iban\Providers\ChainProvider;
use Daycry\Iban\Providers\DatabaseProvider;
use Daycry\Iban\Providers\IbanComProvider;
use Daycry\Iban\Providers\NullProvider;
use Daycry\Iban\Registry\Registry;
use InvalidArgumentException;

/**
 * CI4 service factory for the `daycry/iban` package.
 *
 * Registered for auto-discovery via the `Daycry\Iban\` Composer PSR-4
 * namespace (CI4's `Config\Modules::$discoverInComposer` scans every
 * registered namespace for a `Config/Services.php`), so `service('iban')`
 * resolves without any manual wiring by the consuming application.
 *
 * `Daycry\Iban\Iban` (the facade) and `Daycry\Iban\Config\Iban` (this
 * config) share the class short name `Iban`; both are imported under
 * aliases (`IbanService` / `IbanConfig`) to keep the two unambiguous.
 *
 * @see \Daycry\Iban\Config\Iban
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
class Services extends BaseService
{
    /**
     * Builds the `daycry/iban` facade, wiring the bank-data provider
     * described by {@see IbanConfig::$provider}. For the `'database'`
     * shortcut, the {@see BankModel} backing {@see DatabaseProvider} is
     * built from {@see IbanConfig::$table} / {@see IbanConfig::$dbGroup},
     * so a consuming app's overrides of either are honored.
     *
     * With an empty/unconfigured `Config\Iban` (the package's default),
     * this resolves a fully functional facade backed by
     * {@see NullProvider} — no database setup required.
     */
    public static function iban(bool $getShared = true): IbanService
    {
        if ($getShared) {
            /** @var IbanService $instance */
            $instance = static::getSharedInstance('iban');

            return $instance;
        }

        $config = self::config();

        $provider = match ($config->provider) {
            'null'     => new NullProvider(),
            'database' => new DatabaseProvider(new BankModel($config->table, $config->dbGroup)),
            default    => self::instantiateProvider($config->provider),
        };

        // Opt-in iban.com fallback (Config\Iban::$ibanComApiKey non-empty):
        // chained AFTER the primary provider above, so the remote,
        // paid iban.com API is only queried once the primary provider has
        // already failed to resolve the IBAN.
        if ($config->ibanComApiKey !== '') {
            $provider = new ChainProvider([
                $provider,
                new IbanComProvider($config->ibanComApiKey, service('curlrequest'), $config->ibanComTimeout),
            ]);
        }

        // Opt-in caching layer (Config\Iban::$cacheTtl !== null). Skipped
        // for NullProvider: it never resolves anything, so wrapping it
        // would only add a pointless cache round-trip per resolve() call.
        // A ChainProvider (above) is never a NullProvider, so the combined
        // local+iban.com chain is cached correctly when caching is enabled.
        //
        // NOTE the check is `!== null`, NOT `> 0`: since v2.0, `null` is the
        // "caching disabled" value and `0` means "wrap with a TTL of 0",
        // which CI4's cache handlers treat as "never expires" -- see
        // Config\Iban::$cacheTtl's docblock for the full BREAKING semantics.
        $cacheTtl = $config->cacheTtl;

        if ($cacheTtl !== null && ! $provider instanceof NullProvider) {
            $provider = new CachedProvider($provider, service('cache'), $cacheTtl);
        }

        return new IbanService(new Registry(), $provider);
    }

    /**
     * Resolves the effective {@see IbanConfig}, honoring a consuming app's
     * published `app/Config/Iban.php` override.
     *
     * IMPORTANT — this resolves by the SHORT name `'Iban'`, NOT the package
     * FQCN. CI4's {@see Factories::locateClass()} only prefers the app's
     * `Config\` namespace for a *non-namespaced* alias; requesting
     * `IbanConfig::class` (namespaced) always returns this package's own
     * config and silently ignores a published `Config\Iban extends
     * \Daycry\Iban\Config\Iban`. The short name lets that published override
     * win (via `preferApp`), while still resolving the package default (via
     * the file locator) when nothing is published. The FQCN lookup is a
     * defensive fallback for any unexpected resolution.
     */
    public static function config(): IbanConfig
    {
        $config = Factories::get('config', 'Iban');

        if ($config instanceof IbanConfig) {
            return $config;
        }

        // Defensive fallback to the package's own config by FQCN, for any
        // unexpected short-name resolution.
        $config = Factories::get('config', IbanConfig::class);

        return $config instanceof IbanConfig ? $config : new IbanConfig();
    }

    /**
     * Instantiates a custom {@see ProviderInterface} from its fully
     * qualified class name (the `Config\Iban::$provider` value when it
     * isn't the literal `'null'`/`'database'` shortcut).
     *
     * @throws InvalidArgumentException When `$fqcn` doesn't exist, or doesn't
     *                                    implement {@see ProviderInterface}.
     */
    private static function instantiateProvider(string $fqcn): ProviderInterface
    {
        if (! class_exists($fqcn)) {
            throw new InvalidArgumentException(sprintf(
                'Config\Iban::$provider "%s" is not a valid class.',
                $fqcn,
            ));
        }

        $provider = new $fqcn();

        if (! $provider instanceof ProviderInterface) {
            throw new InvalidArgumentException(sprintf(
                'Config\Iban::$provider "%s" must implement %s.',
                $fqcn,
                ProviderInterface::class,
            ));
        }

        return $provider;
    }
}
