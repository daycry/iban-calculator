<?php

declare(strict_types=1);

namespace Daycry\Iban\Import;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Models\BankModel;

/**
 * Runs a single {@see ImporterInterface} against the `banks` table
 * (via {@see BankModel}): fetches its rows, normalizes them into `banks`
 * columns with provenance (`country_code`/`source_id`/`source_license`/
 * `source_version`/`updated_at`), and upserts each one by its natural key
 * `(country_code, bank_code, branch_code)`.
 *
 * CI4-dependent (uses {@see BankModel}), unlike the rest of the importer
 * framework (`Contracts\ImporterInterface`, `Import\ImportReport`,
 * `Import\ImporterRegistry`) -- that's why it lives under `src/Import/`
 * rather than a framework-free directory; `src/Import/` is not covered by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`'s guard.
 *
 * @see \Daycry\Iban\Commands\UpdateCommand
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
class ImportRunner
{
    /**
     * @param ImporterInterface $importer  The (country, source) importer to run.
     * @param BankModel         $model     The `banks` table gateway to upsert into.
     * @param bool              $dryRun    When `true`, counts what *would* be
     *                                       imported/skipped but writes nothing.
     * @param string|null       $localFile When given, passed straight through
     *                                       to {@see ImporterInterface::rows()}
     *                                       for an offline import from a
     *                                       previously-downloaded file.
     */
    public function run(
        ImporterInterface $importer,
        BankModel $model,
        bool $dryRun = false,
        ?string $localFile = null,
    ): ImportReport {
        $countryCode = $importer->countryCode();
        $sourceId    = $importer->sourceId();
        $license     = $importer->license();

        // One timestamp/version stamp per run (not per row), so every row
        // written by this call carries the same "when was this imported"
        // marker instead of drifting row-to-row.
        $updatedAt = date('Y-m-d H:i:s');
        $version   = substr($updatedAt, 0, 10);

        $fetched  = 0;
        $imported = 0;
        $skipped  = 0;
        $messages = [];

        foreach ($importer->rows($localFile) as $row) {
            $fetched++;

            $bankCode = $row['bank_code'] ?? null;

            if (! is_string($bankCode) || $bankCode === '') {
                $skipped++;
                $messages[] = sprintf('Skipped row #%d: missing or empty "bank_code".', $fetched);

                continue;
            }

            $branchCodeRaw = $row['branch_code'] ?? null;
            $branchCode    = is_string($branchCodeRaw) && $branchCodeRaw !== '' ? $branchCodeRaw : null;

            if ($dryRun) {
                $imported++;

                continue;
            }

            $data = [
                'country_code'   => $countryCode,
                'bank_code'      => $bankCode,
                'branch_code'    => $branchCode,
                'bic'            => self::stringOrNull($row['bic'] ?? null),
                'name'           => self::stringOrNull($row['name'] ?? null),
                'short_name'     => self::stringOrNull($row['short_name'] ?? null),
                'city'           => self::stringOrNull($row['city'] ?? null),
                'address'        => self::stringOrNull($row['address'] ?? null),
                'sepa_sct'       => self::tinyIntOrNull($row['sepa_sct'] ?? null),
                'sepa_sct_inst'  => self::tinyIntOrNull($row['sepa_sct_inst'] ?? null),
                'sepa_sdd_core'  => self::tinyIntOrNull($row['sepa_sdd_core'] ?? null),
                'sepa_sdd_b2b'   => self::tinyIntOrNull($row['sepa_sdd_b2b'] ?? null),
                'source_id'      => $sourceId,
                'source_version' => $version,
                'source_license' => $license,
                'updated_at'     => $updatedAt,
            ];

            $existing   = $model->findByNaturalKey($countryCode, $bankCode, $branchCode);
            $existingId = $existing['id'] ?? null;

            if (is_int($existingId) || is_string($existingId)) {
                $model->update($existingId, $data);
            } else {
                $model->insert($data);
            }

            $imported++;
        }

        return new ImportReport(
            countryCode: $countryCode,
            sourceId: $sourceId,
            fetched: $fetched,
            imported: $imported,
            skipped: $skipped,
            dryRun: $dryRun,
            messages: $messages,
        );
    }

    /**
     * Maps an optional row value to a nullable string, per
     * {@see ImporterInterface::rows()}'s documented `string|null` contract
     * for these columns -- anything else (including `null`) becomes `null`.
     */
    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    /**
     * Maps an optional row value to the `TINYINT(1)` representation the
     * `banks` table stores SEPA scheme flags in, per
     * {@see ImporterInterface::rows()}'s documented `bool|null` contract for
     * these columns -- anything else (including `null`) becomes `null`.
     */
    private static function tinyIntOrNull(mixed $value): ?int
    {
        return is_bool($value) ? ($value ? 1 : 0) : null;
    }
}
