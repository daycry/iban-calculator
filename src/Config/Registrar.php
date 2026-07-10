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
 * that same discovery mechanism without this Registrar.
 *
 * This class intentionally declares NO `Autoload()` method. CI4's
 * Registrar-merging only applies to config classes that extend
 * `CodeIgniter\Config\BaseConfig` (e.g. `Config\Autoload` in a consuming
 * app); `Config\Autoload` itself does NOT extend `BaseConfig`, so a
 * Registrar `Autoload()` hook here would never actually be merged in or
 * fire — it would be dead code (see T-37/39 findings).
 *
 * The bundled `src/Helpers/iban_helper.php` is loaded on demand instead:
 * call `helper('iban')` and CI4's helper locator finds it via this
 * package's `Daycry\Iban\` => `src/` PSR-4 namespace mapping, no extra
 * setup required. A consuming app that wants it always-on can add
 * `'iban'` to its own `Config\Autoload::$helpers` array.
 *
 * @see \Daycry\Iban\Config\Services
 * @see src/Helpers/iban_helper.php
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
class Registrar
{
}
