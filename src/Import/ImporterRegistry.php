<?php

declare(strict_types=1);

namespace Daycry\Iban\Import;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\BancoDeEspanaImporter;
use Daycry\Iban\Import\Importers\BetaalverenigingImporter;
use Daycry\Iban\Import\Importers\BundesbankImporter;
use Daycry\Iban\Import\Importers\OenbImporter;
use Daycry\Iban\Import\Importers\SixImporter;

/**
 * In-memory catalog of {@see ImporterInterface} instances, keyed by their
 * natural `(countryCode, sourceId)` pair.
 *
 * FRAMEWORK-FREE: no `codeigniter4/*` dependency, so it can be constructed
 * and queried outside of a CI4 application too (`iban:update` just happens
 * to be the CI4 consumer in this package).
 *
 * {@see self::registerDefaults()} was intentionally empty in v1.1's V-6 --
 * that task only built the framework. v1.1's V-7a registered the first two
 * bundled official-source importers there -- {@see \Daycry\Iban\Import\Importers\OenbImporter}
 * (AT) and {@see \Daycry\Iban\Import\Importers\BundesbankImporter} (DE) --
 * and v1.1's V-7b adds three more -- {@see \Daycry\Iban\Import\Importers\SixImporter}
 * (CH), {@see \Daycry\Iban\Import\Importers\BetaalverenigingImporter} (NL)
 * and {@see \Daycry\Iban\Import\Importers\BancoDeEspanaImporter} (ES) -- so
 * `new ImporterRegistry()` picks up all five automatically for every
 * consumer (`iban:update` included) without any other call site changing.
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
     * Was deliberately empty in v1.1's V-6 (the importer framework itself
     * had nothing to bundle yet). v1.1's V-7a filled this in with the first
     * two concrete official-source importers -- {@see OenbImporter} (AT) and
     * {@see BundesbankImporter} (DE). v1.1's V-7b adds three more --
     * {@see SixImporter} (CH), {@see BetaalverenigingImporter} (NL) and
     * {@see BancoDeEspanaImporter} (ES) -- so every consumer (`iban:update`
     * included) picks up all five automatically without any other call site
     * changing.
     */
    protected function registerDefaults(): void
    {
        $this->register(new OenbImporter());
        $this->register(new BundesbankImporter());
        $this->register(new SixImporter());
        $this->register(new BetaalverenigingImporter());
        $this->register(new BancoDeEspanaImporter());
    }

    private static function key(string $countryCode, string $sourceId): string
    {
        return strtoupper($countryCode) . ':' . strtolower($sourceId);
    }
}
