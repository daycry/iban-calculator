<?php

declare(strict_types=1);

namespace Daycry\Iban\Import;

/**
 * Immutable result of a single {@see ImportRunner::run()} call: how many
 * rows a {@see \Daycry\Iban\Contracts\ImporterInterface} yielded, how many
 * were actually written (or would have been, for `--dry-run`), and how many
 * were skipped (e.g. missing the required `bank_code`).
 *
 * FRAMEWORK-FREE: plain readonly DTO, no `codeigniter4/*` dependency --
 * mirrors {@see \Daycry\Iban\DTO\BankInfo} / {@see \Daycry\Iban\DTO\ValidationResult}.
 * It lives under `src/Import/` rather than `src/DTO/` because it's part of
 * the importer *framework* (paired with `ImporterRegistry`/`ImportRunner`),
 * not a core value object; `src/Import/` isn't covered by the framework-free
 * architecture guard, but this class holds to the same discipline anyway.
 *
 * @see ImportRunner
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final readonly class ImportReport
{
    /**
     * @param string   $countryCode ISO 3166-1 alpha-2 country code the import ran for.
     * @param string   $sourceId    The importer's {@see \Daycry\Iban\Contracts\ImporterInterface::sourceId()}.
     * @param int      $fetched     Total rows yielded by the importer.
     * @param int      $imported    Rows written (or, for `--dry-run`, that would have been written).
     * @param int      $skipped     Rows skipped, e.g. missing a required field.
     * @param bool     $dryRun      Whether this report describes a preview run (nothing was written).
     * @param string[] $messages    Human-readable notes (e.g. why a row was skipped).
     */
    public function __construct(
        public string $countryCode,
        public string $sourceId,
        public int $fetched,
        public int $imported,
        public int $skipped,
        public bool $dryRun,
        public array $messages = [],
    ) {
    }
}
