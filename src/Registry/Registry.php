<?php

declare(strict_types=1);

namespace Daycry\Iban\Registry;

use Daycry\Iban\Contracts\RegistryLoaderInterface;
use OutOfBoundsException;

/**
 * Central lookup for IBAN structural country data.
 *
 * Hydrates the raw registry data (obtained via an injected
 * {@see RegistryLoaderInterface}, defaulting to {@see PhpRegistryLoader}) into
 * {@see CountryStructure} DTOs, caching the hydrated map so repeated lookups
 * don't re-hydrate.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §6.1
 */
final class Registry
{
    /**
     * Registry version marker.
     *
     * Independently authored structural facts (IBAN lengths / field offsets /
     * BBAN tokens), compiled from publicly documented IBAN formats. This
     * registry is NOT derived from, nor copied out of, the SWIFT IBAN
     * Registry file.
     */
    public const string VERSION = '2026-07 (independent authorship; not derived from the SWIFT IBAN Registry)';

    /**
     * Hydrated country map, lazily built and cached on first access.
     *
     * @var array<string, CountryStructure>|null
     */
    private ?array $cache = null;

    public function __construct(
        private readonly RegistryLoaderInterface $loader = new PhpRegistryLoader(),
    ) {
    }

    /**
     * Whether a structure is registered for the given country code.
     *
     * The lookup is case-insensitive (`$cc` is normalized to uppercase).
     */
    public function has(string $cc): bool
    {
        return array_key_exists(strtoupper($cc), $this->countries());
    }

    /**
     * Fetch the structure for the given country code.
     *
     * The lookup is case-insensitive (`$cc` is normalized to uppercase).
     *
     * @throws OutOfBoundsException When no structure is registered for `$cc`.
     */
    public function get(string $cc): CountryStructure
    {
        $code      = strtoupper($cc);
        $countries = $this->countries();

        if (!array_key_exists($code, $countries)) {
            throw new OutOfBoundsException(sprintf('No IBAN structure registered for country code "%s".', $code));
        }

        return $countries[$code];
    }

    /**
     * The full hydrated registry map, keyed by ISO 3166-1 alpha-2 country code.
     *
     * @return array<string, CountryStructure>
     */
    public function all(): array
    {
        return $this->countries();
    }

    /**
     * @return array<string, CountryStructure>
     */
    private function countries(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->hydrate($this->loader->load());
        }

        return $this->cache;
    }

    /**
     * Hydrate the raw loader output into {@see CountryStructure} DTOs.
     *
     * @param array<string, array<string, mixed>> $raw
     *
     * @return array<string, CountryStructure>
     */
    private function hydrate(array $raw): array
    {
        $countries = [];

        foreach ($raw as $countryCode => $data) {
            $code = strtoupper($countryCode);

            /** @var array{0:int,1:int} $bank */
            $bank = $data['bank'];

            /** @var array{0:int,1:int}|null $branch */
            $branch = $data['branch'] ?? null;

            /** @var array{0:int,1:int} $account */
            $account = $data['account'];

            /** @var array{0:int,1:int}|null $nationalCheck */
            $nationalCheck = $data['national_check'] ?? null;

            $countries[$code] = new CountryStructure(
                countryCode: $code,
                ibanLength: (int) $data['iban_length'],
                bbanStructure: (string) $data['bban_structure'],
                bank: $bank,
                branch: $branch,
                account: $account,
                nationalCheck: $nationalCheck,
                sepa: (bool) $data['sepa'],
                ibanExampleElectronic: (string) $data['example'],
            );
        }

        return $countries;
    }
}
