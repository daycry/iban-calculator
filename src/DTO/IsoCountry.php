<?php

declare(strict_types=1);

namespace Daycry\Iban\DTO;

/**
 * DTO modelling a single ISO 3166-1 country.
 *
 * Pure data holder hydrated by {@see \Daycry\Iban\Registry\IsoCountryRegistry}
 * from the compiled `data/iso_countries.php` list (or an alternative
 * {@see \Daycry\Iban\Contracts\IsoCountryLoaderInterface} source). Framework-free
 * by design so it stays usable outside CodeIgniter.
 */
final readonly class IsoCountry
{
    /**
     * @param string $alpha2  Two-letter ISO 3166-1 alpha-2 code, uppercase (e.g. 'ES').
     * @param string $name    Common English country name (e.g. 'Spain').
     * @param string $alpha3  Three-letter ISO 3166-1 alpha-3 code, uppercase (e.g. 'ESP').
     * @param string $numeric Three-digit ISO 3166-1 numeric code, zero-padded (e.g. '724').
     */
    public function __construct(
        public string $alpha2,
        public string $name,
        public string $alpha3,
        public string $numeric,
    ) {
    }
}
