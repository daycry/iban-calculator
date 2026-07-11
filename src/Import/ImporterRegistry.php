<?php

declare(strict_types=1);

namespace Daycry\Iban\Import;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * In-memory catalog of {@see ImporterInterface} instances, keyed by their
 * natural `(countryCode, sourceId)` pair.
 *
 * FRAMEWORK-FREE: no `codeigniter4/*` dependency, so it can be constructed
 * and queried outside of a CI4 application too (`iban:update` just happens
 * to be the CI4 consumer in this package).
 *
 * {@see self::registerDefaults()} is intentionally empty in v1.1's V-6 --
 * this task only builds the framework. v1.1's V-7 registers the bundled
 * official-source importers (OeNB/SIX/Bundesbank/...) there, so `new
 * ImporterRegistry()` picks them up automatically for every consumer
 * (`iban:update` included) without any other call site changing.
 *
 * @see \Daycry\Iban\Commands\UpdateCommand
 * @see ImportRunner
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
class ImporterRegistry
{
    /** @var array<string, ImporterInterface> keyed by `"{COUNTRY}:{source}"` (see {@see self::key()}) */
    private array $importers = [];

    public function __construct()
    {
        $this->registerDefaults();
    }

    /**
     * Registers (or replaces, if the same country+source is already
     * registered) an importer.
     */
    public function register(ImporterInterface $importer): void
    {
        $this->importers[self::key($importer->countryCode(), $importer->sourceId())] = $importer;
    }

    /**
     * @return list<ImporterInterface> Every registered importer, in registration order.
     */
    public function all(): array
    {
        return array_values($this->importers);
    }

    /**
     * @return list<ImporterInterface> Registered importers for `$countryCode` (case-insensitive), in registration order.
     */
    public function forCountry(string $countryCode): array
    {
        $needle = strtoupper($countryCode);

        return array_values(array_filter(
            $this->importers,
            static fn (ImporterInterface $importer): bool => strtoupper($importer->countryCode()) === $needle,
        ));
    }

    /**
     * Looks up a single importer by its exact `(countryCode, sourceId)` pair
     * (case-insensitive on both), or `null` if none is registered.
     */
    public function get(string $countryCode, string $sourceId): ?ImporterInterface
    {
        return $this->importers[self::key($countryCode, $sourceId)] ?? null;
    }

    /**
     * Summarizes every registered importer for display (e.g. `iban:update`'s
     * no-selection listing), without exposing the importer instances
     * themselves.
     *
     * @return list<array{country: string, source: string, name: string, license: string}>
     */
    public function sources(): array
    {
        return array_map(
            static fn (ImporterInterface $importer): array => [
                'country' => $importer->countryCode(),
                'source'  => $importer->sourceId(),
                'name'    => $importer->sourceName(),
                'license' => $importer->license(),
            ],
            $this->all(),
        );
    }

    /**
     * Registers this package's bundled default importers.
     *
     * Deliberately empty in v1.1's V-6 (the importer framework itself has
     * nothing to bundle yet): v1.1's V-7 is the clear extension point that
     * fills this in with the concrete official-source importers.
     */
    protected function registerDefaults(): void
    {
    }

    private static function key(string $countryCode, string $sourceId): string
    {
        return strtoupper($countryCode) . ':' . strtolower($sourceId);
    }
}
