<?php

declare(strict_types=1);

namespace Daycry\Iban\Contracts;

/**
 * Contract for loading the raw ISO 3166-1 country registry data.
 *
 * Mirrors {@see RegistryLoaderInterface} for the IBAN structural registry:
 * {@see \Daycry\Iban\Registry\IsoCountryRegistry} accepts any implementation,
 * so the country data can come from the bundled compiled PHP list
 * ({@see \Daycry\Iban\Registry\PhpIsoCountryLoader}) or an alternative source
 * such as a database table
 * ({@see \Daycry\Iban\Providers\DatabaseIsoCountryLoader}).
 */
interface IsoCountryLoaderInterface
{
    /**
     * Load the raw ISO 3166-1 country data, keyed by alpha-2 code.
     *
     * @return array<string, array{name: string, alpha3: string, numeric: string}>
     */
    public function load(): array;
}
