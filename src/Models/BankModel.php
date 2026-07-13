<?php

declare(strict_types=1);

namespace Daycry\Iban\Models;

use CodeIgniter\Model;

/**
 * Maps the `banks` table (see the `CreateBanksTable` migration).
 *
 * Rows are looked up by their natural key `(country_code, bank_code,
 * branch_code)`, not by the auto-increment `id`. `$table` defaults to
 * `'banks'` and `$DBGroup` is left unset by default so it defers to
 * whatever `Config\Database::$defaultGroup` resolves to for the
 * consumer/test environment (e.g. `tests` under CodeIgniter's testing
 * bootstrap) — but both are overridable via the constructor, which is how
 * {@see \Daycry\Iban\Config\Services::iban()} wires up
 * `Config\Iban::$table` / `Config\Iban::$dbGroup`.
 *
 * @see \Daycry\Iban\Database\Migrations\CreateBanksTable
 * @see \Daycry\Iban\Providers\DatabaseProvider
 * @see \Daycry\Iban\Config\Services::iban()
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
     * @param string|null $table   Overrides the queried table (default: `'banks'`)
     *                              when non-null, e.g. from `Config\Iban::$table`.
     * @param string|null $dbGroup Overrides the `Config\Database` connection group
     *                              (default: deferred to `Config\Database::$defaultGroup`)
     *                              when non-empty, e.g. from `Config\Iban::$dbGroup`. Must be
     *                              set before `parent::__construct()`, which is what actually
     *                              opens the connection via `Database::connect($this->DBGroup)`.
     *                              An empty string is treated the same as `null` (no override),
     *                              matching `BaseModel::$DBGroup`'s own `non-empty-string|null` type.
     */
    public function __construct(?string $table = null, ?string $dbGroup = null)
    {
        if ($dbGroup !== null && $dbGroup !== '') {
            $this->DBGroup = $dbGroup;
        }

        if ($table !== null) {
            $this->table = $table;
        }

        parent::__construct();
    }

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

    /**
     * Finds a single `banks` row by BIC, using the indexed `bic` column.
     *
     * Matching rule — **BIC8**: a stored BIC may be 8 chars (institution head
     * office) or 11 chars (with a branch segment), and the caller may pass
     * either. We match on the first 8 characters (the BIC8) so the lookup is
     * symmetric: an 11-char query resolves an 8-char row and an 8-char query
     * resolves an 11-char row — both denote the same institution office. This
     * is a prefix match (`bic LIKE 'BIC8%'`), which the plain index on `bic`
     * (see the `CreateBanksTable` migration's `addKey('bic')`) can serve.
     *
     * The input is normalized first (whitespace stripped, uppercased); stored
     * BICs are uppercase by convention (produced that way by the bundled
     * importers), so the comparison is effectively case-insensitive. When more
     * than one row shares the BIC8 (e.g. several branch rows), the first is
     * returned — any of them identifies the same bank.
     *
     * @return array<string, mixed>|null The raw row, or null if no match.
     */
    public function findByBic(string $bic): ?array
    {
        $bic8 = substr(strtoupper((string) preg_replace('/\s+/', '', $bic)), 0, 8);

        if ($bic8 === '') {
            return null;
        }

        $row = $this->like('bic', $bic8, 'after')->first();

        return is_array($row) ? $row : null;
    }
}
