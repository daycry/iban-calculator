<?php

declare(strict_types=1);

namespace Daycry\Iban\Registry;

use Daycry\Iban\Contracts\IsoCountryLoaderInterface;
use Daycry\Iban\DTO\IsoCountry;
use OutOfBoundsException;

/**
 * Central lookup for ISO 3166-1 country data.
 *
 * Mirrors {@see Registry} (the IBAN structural registry): it hydrates the raw
 * data obtained via an injected {@see IsoCountryLoaderInterface} (defaulting to
 * {@see PhpIsoCountryLoader}, the bundled compiled list) into {@see IsoCountry}
 * DTOs, caching the hydrated map so repeated lookups don't re-hydrate.
 *
 * This exists because BIC validation must recognise ALL countries that have
 * BICs, not just the ~78 that issue IBANs: the country code in a BIC
 * (positions 5-6) is checked against this full ISO 3166-1 set, whereas the
 * IBAN {@see Registry} only knows the IBAN-issuing subset.
 */
final class IsoCountryRegistry
{
    /**
     * Registry version marker.
     *
     * The ISO 3166-1 alpha-2/alpha-3/numeric assignments are public facts
     * published by the ISO 3166 Maintenance Agency; this registry was
     * independently authored from that public knowledge and is NOT copied or
     * derived from any licensed compilation. Covers the 249 officially
     * assigned alpha-2 codes; user-assigned codes (e.g. `XK`) are excluded by
     * design (BIC validation layers `XK` separately).
     */
    public const string VERSION = '2026-07 (249 officially assigned ISO 3166-1 codes; independently authored from public facts, not derived from a licensed compilation)';

    /**
     * Hydrated country map, lazily built and cached on first access.
     *
     * @var array<string, IsoCountry>|null
     */
    private ?array $cache = null;

    public function __construct(
        private readonly IsoCountryLoaderInterface $loader = new PhpIsoCountryLoader(),
    ) {
    }

    /**
     * Whether a country is registered for the given alpha-2 code.
     *
     * The lookup is case-insensitive (`$alpha2` is normalized to uppercase).
     */
    public function has(string $alpha2): bool
    {
        return array_key_exists(strtoupper($alpha2), $this->countries());
    }

    /**
     * Fetch the country for the given alpha-2 code.
     *
     * The lookup is case-insensitive (`$alpha2` is normalized to uppercase).
     *
     * @throws OutOfBoundsException When no country is registered for `$alpha2`.
     */
    public function get(string $alpha2): IsoCountry
    {
        $code      = strtoupper($alpha2);
        $countries = $this->countries();

        if (!array_key_exists($code, $countries)) {
            throw new OutOfBoundsException(sprintf('No ISO 3166-1 country registered for alpha-2 code "%s".', $code));
        }

        return $countries[$code];
    }

    /**
     * The full hydrated country map, keyed by ISO 3166-1 alpha-2 code.
     *
     * @return array<string, IsoCountry>
     */
    public function all(): array
    {
        return $this->countries();
    }

    /**
     * The number of registered countries.
     */
    public function count(): int
    {
        return count($this->countries());
    }

    /**
     * @return array<string, IsoCountry>
     */
    private function countries(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->hydrate($this->loader->load());
        }

        return $this->cache;
    }

    /**
     * Hydrate the raw loader output into {@see IsoCountry} DTOs.
     *
     * @param array<string, array{name: string, alpha3: string, numeric: string}> $raw
     *
     * @return array<string, IsoCountry>
     */
    private function hydrate(array $raw): array
    {
        $countries = [];

        foreach ($raw as $alpha2 => $data) {
            $code = strtoupper($alpha2);

            $countries[$code] = new IsoCountry(
                alpha2: $code,
                name: (string) $data['name'],
                alpha3: (string) $data['alpha3'],
                numeric: (string) $data['numeric'],
            );
        }

        return $countries;
    }
}
