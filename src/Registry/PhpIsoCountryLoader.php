<?php

declare(strict_types=1);

namespace Daycry\Iban\Registry;

use Daycry\Iban\Contracts\IsoCountryLoaderInterface;

/**
 * Default {@see IsoCountryLoaderInterface} implementation.
 *
 * Loads the compiled ISO 3166-1 country registry from the bundled
 * `data/iso_countries.php` file, resolved relative to this loader (via
 * `__DIR__`) so it works regardless of the caller's current working directory.
 * This is the zero-dependency default — no database, no configuration — that
 * keeps {@see IsoCountryRegistry} usable standalone, outside CodeIgniter.
 *
 * Intentionally swappable: {@see IsoCountryRegistry} accepts any
 * {@see IsoCountryLoaderInterface}, so consumers may plug in an alternative
 * source (e.g. {@see \Daycry\Iban\Providers\DatabaseIsoCountryLoader}) without
 * touching `IsoCountryRegistry` itself.
 */
final class PhpIsoCountryLoader implements IsoCountryLoaderInterface
{
    /**
     * Load the raw ISO 3166-1 country data from `data/iso_countries.php`.
     *
     * @return array<string, array{name: string, alpha3: string, numeric: string}> Raw data keyed by alpha-2 code.
     */
    public function load(): array
    {
        /** @var array<string, array{name: string, alpha3: string, numeric: string}> $data */
        $data = require __DIR__ . '/data/iso_countries.php';

        return $data;
    }
}
