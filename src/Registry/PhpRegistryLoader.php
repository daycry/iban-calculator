<?php

declare(strict_types=1);

namespace Daycry\Iban\Registry;

use Daycry\Iban\Contracts\RegistryLoaderInterface;

/**
 * Default {@see RegistryLoaderInterface} implementation.
 *
 * Loads the raw IBAN structure registry data from the bundled
 * `data/countries.php` file, resolved relative to this loader so it works
 * regardless of the caller's current working directory.
 *
 * Intentionally swappable: {@see Registry} accepts any
 * {@see RegistryLoaderInterface}, so consumers may plug in an alternative
 * source (e.g. a compiled/cached registry) without touching `Registry` itself.
 */
final class PhpRegistryLoader implements RegistryLoaderInterface
{
    /**
     * Load the raw registry data from `data/countries.php`.
     *
     * @return array<string, array<string, mixed>> Raw registry data keyed by ISO 3166-1 alpha-2 country code.
     */
    public function load(): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $data = require __DIR__ . '/data/countries.php';

        return $data;
    }
}
