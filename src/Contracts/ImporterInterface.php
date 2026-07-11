<?php

declare(strict_types=1);

namespace Daycry\Iban\Contracts;

/**
 * Contract for a bank-data importer: one (country, source) pair that knows
 * how to yield normalized `banks` rows either from its official live source
 * or from a local, previously-downloaded file (offline import).
 *
 * FRAMEWORK-FREE: this interface must never depend on the framework package
 * this library adapts for (see `tests/Architecture/CoreIsFrameworkFreeTest.php`,
 * which guards the whole `Contracts` directory). Concrete importers
 * implementing `rows()` should therefore fetch over the network using plain
 * PHP (`file_get_contents()`, streams, `curl`, ...) rather than a
 * framework-provided HTTP client, so this contract -- and anything written
 * purely against it -- stays usable standalone.
 *
 * Concrete official-source importers (OeNB/SIX/Bundesbank/...) are added in
 * v1.1's V-7 and register themselves with {@see \Daycry\Iban\Import\ImporterRegistry}.
 * V-6 only defines the contract plus the framework (`ImporterRegistry`,
 * `ImportRunner`, `ImportReport`) that consumes it, proven here with a fake
 * test importer.
 *
 * @see \Daycry\Iban\Import\ImportRunner
 * @see \Daycry\Iban\Import\ImporterRegistry
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
interface ImporterInterface
{
    /**
     * ISO 3166-1 alpha-2 country code this importer supplies data for, e.g.
     * `'AT'`.
     */
    public function countryCode(): string;

    /**
     * Stable, machine-friendly identifier for the data source, e.g. `'oenb'`.
     * Stored verbatim into `banks.source_id` for every row this importer
     * produces, and used together with {@see self::countryCode()} as the
     * natural key {@see \Daycry\Iban\Import\ImporterRegistry} registers
     * importers under.
     */
    public function sourceId(): string;

    /**
     * Human-readable name of the data source/publisher, e.g.
     * `'Oesterreichische Nationalbank'`. Printed by `iban:update` alongside
     * the license/attribution notice.
     */
    public function sourceName(): string;

    /**
     * License/attribution string to record on every imported row
     * (`banks.source_license`) and to print in `iban:update`'s output, e.g.
     * `'CC-BY 4.0'` or `'proprietary — attribution required'`.
     */
    public function license(): string;

    /**
     * Official download URL for this source's data, used both as
     * documentation (printed by `iban:update`) and as the default live-fetch
     * location when {@see self::rows()} is called without `$localFile`.
     */
    public function sourceUrl(): string;

    /**
     * Yields normalized bank rows ready to be written into the `banks`
     * table (see {@see \Daycry\Iban\Import\ImportRunner}, which adds the
     * provenance columns -- `country_code`, `source_id`, `source_license`,
     * `source_version`, `updated_at` -- itself).
     *
     * Recognized keys per yielded row:
     * - `bank_code` (string, required)
     * - `branch_code` (string|null, optional)
     * - `bic` (string|null, optional)
     * - `name` (string|null, optional)
     * - `short_name` (string|null, optional)
     * - `city` (string|null, optional)
     * - `address` (string|null, optional)
     * - `sepa_sct` (bool|null, optional)
     * - `sepa_sct_inst` (bool|null, optional)
     * - `sepa_sdd_core` (bool|null, optional)
     * - `sepa_sdd_b2b` (bool|null, optional)
     *
     * When `$localFile` is given, the importer MUST parse that file instead
     * of reaching out over the network (offline import, e.g. `iban:update
     * --file=...`); when it's `null`, the importer fetches live from
     * {@see self::sourceUrl()}.
     *
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable;
}
