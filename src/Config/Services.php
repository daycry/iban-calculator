<?php

declare(strict_types=1);

namespace Daycry\Iban\Config;

use CodeIgniter\Config\BaseService;
use CodeIgniter\Config\Factories;
use Daycry\Iban\Config\Iban as IbanConfig;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\Iban as IbanService;
use Daycry\Iban\Models\BankModel;
use Daycry\Iban\Providers\DatabaseProvider;
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

        // Equivalent to `config(IbanConfig::class)`, spelled out via the
        // underlying `Factories` call: lets a consuming app's published
        // `App\Config\Iban` override this package's default, while
        // resolving through a real, PHPStan-visible method (the global
        // `config()`/`helper()` functions live in a CI4 file this
        // package's `phpstan.neon` doesn't scan).
        $config = Factories::get('config', IbanConfig::class);

        if (! $config instanceof IbanConfig) {
            $config = new IbanConfig();
        }

        $provider = match ($config->provider) {
            'null'     => new NullProvider(),
            'database' => new DatabaseProvider(new BankModel($config->table, $config->dbGroup)),
            default    => self::instantiateProvider($config->provider),
        };

        return new IbanService(new Registry(), $provider);
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
