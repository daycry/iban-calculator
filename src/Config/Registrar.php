<?php

declare(strict_types=1);

namespace Daycry\Iban\Config;

/**
 * CI4 Registrar hook for the `daycry/iban` package.
 *
 * `Config\Modules::$discoverInComposer` (enabled by default) makes CI4
 * auto-discover this class from the `Daycry\Iban\` Composer namespace, same
 * as `Config\Services` — no manual wiring needed by the consuming
 * application. `service('iban')` and any package commands already work via
 * that same discovery mechanism without this Registrar; its only purpose
 * here is to opt the bundled `iban` helper into auto-loading.
 *
 * NOTE: `src/Helpers/iban_helper.php` doesn't exist yet (arrives in T-40).
 * Referencing `'iban'` by name here is safe in the meantime — nothing
 * dereferences this array until a config class extending `BaseConfig`
 * actually merges it in, which only happens once the helper file exists
 * and something calls `helper('iban')`.
 */
class Registrar
{
    /**
     * @return array<string, list<string>>
     */
    public static function Autoload(): array
    {
        return ['helpers' => ['iban']];
    }
}
