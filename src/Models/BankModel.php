<?php

declare(strict_types=1);

namespace Daycry\Iban\Models;

use CodeIgniter\Model;

/**
 * Maps the `banks` table (see the `CreateBanksTable` migration).
 *
 * Rows are looked up by their natural key `(country_code, bank_code,
 * branch_code)`, not by the auto-increment `id`. `$DBGroup` is intentionally
 * left unset so it defers to whatever `Config\Database::$defaultGroup`
 * resolves to for the consumer/test environment (e.g. `tests` under
 * CodeIgniter's testing bootstrap), instead of hardcoding a group here.
 *
 * @see \Daycry\Iban\Database\Migrations\CreateBanksTable
 * @see \Daycry\Iban\Providers\DatabaseProvider
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §7
 */
class BankModel extends Model
{
    protected $table         = 'banks';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    /** @var list<string> */
    protected $allowedFields = [
        'country_code',
        'bank_code',
        'branch_code',
        'bic',
        'name',
        'short_name',
        'city',
        'address',
        'sepa_sct',
        'sepa_sct_inst',
        'sepa_sdd_core',
        'sepa_sdd_b2b',
        'source_id',
        'source_version',
        'source_license',
        'updated_at',
    ];

    /**
     * Finds a single `banks` row by its natural key.
     *
     * When `$branchCode` is `null`, matches rows where `branch_code IS NULL`
     * (countries without a branch segment, e.g. DE/NL/BE) rather than being
     * ignored as a filter.
     *
     * @return array<string, mixed>|null The raw row, or null if no match.
     */
    public function findByNaturalKey(string $countryCode, string $bankCode, ?string $branchCode): ?array
    {
        $query = $this->where('country_code', $countryCode)
            ->where('bank_code', $bankCode);

        if ($branchCode === null) {
            $query->where('branch_code', null);
        } else {
            $query->where('branch_code', $branchCode);
        }

        $row = $query->first();

        return is_array($row) ? $row : null;
    }
}
