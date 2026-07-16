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
     * @param bool              $prune     When `true`, after upserting this
     *                                       run's rows, DELETE any pre-existing
     *                                       row for the same `(country_code,
     *                                       source_id)` that this run did not
     *                                       produce (a full sync of that
     *                                       source's slice). Guarded: pruning is
     *                                       skipped entirely when the run
     *                                       imported nothing, so a failed/empty
     *                                       fetch can never wipe good data. In
     *                                       `--dry-run` the would-prune count is
     *                                       reported but nothing is deleted.
     */
    public function run(
        ImporterInterface $importer,
        BankModel $model,
        bool $dryRun = false,
        ?string $localFile = null,
        bool $prune = false,
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

        /** @var array<string, true> $seen Natural keys (bank_code + branch_code) produced by this run, for `--prune`. */
        $seen = [];

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

            $seen[$bankCode . "\x1f" . ($branchCode ?? '')] = true;

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

        // --prune: delete rows this source previously wrote for this country
        // that the current run no longer produced. Guarded by `$imported > 0`
        // so an empty/failed fetch never wipes existing data; in --dry-run the
        // count is computed but nothing is deleted.
        $pruned = 0;

        if ($prune && $imported > 0) {
            $staleIds = [];

            foreach ($model->rowsForSource($countryCode, $sourceId) as $existing) {
                $bank   = $existing['bank_code'] ?? null;
                $branch = $existing['branch_code'] ?? null;
                $key    = (is_string($bank) ? $bank : '') . "\x1f" . (is_string($branch) ? $branch : '');

                if (! isset($seen[$key])) {
                    $staleIds[] = $existing['id'];
                }
            }

            $pruned = count($staleIds);

            if (! $dryRun && $staleIds !== []) {
                $model->delete($staleIds);
            }
        }

        return new ImportReport(
            countryCode: $countryCode,
            sourceId: $sourceId,
            fetched: $fetched,
            imported: $imported,
            skipped: $skipped,
            dryRun: $dryRun,
            messages: $messages,
            pruned: $pruned,
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
