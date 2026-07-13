<?php

declare(strict_types=1);

namespace Daycry\Iban\Models;

use CodeIgniter\Model;

/**
 * Maps the `iso_countries` table (see the `CreateIsoCountriesTable` migration).
 *
 * Backs the optional database-backed ISO 3166-1 source
 * ({@see \Daycry\Iban\Providers\DatabaseIsoCountryLoader}). `$table` defaults
 * to `'iso_countries'` and `$DBGroup` is left unset by default so it defers to
 * whatever `Config\Database::$defaultGroup` resolves to for the consumer/test
 * environment (e.g. `tests` under CodeIgniter's testing bootstrap) — but both
 * are overridable via the constructor, which is how
 * {@see \Daycry\Iban\Config\Services::isoCountries()} wires up
 * `Config\Iban::$isoCountryTable` / `Config\Iban::$dbGroup`.
 *
 * @see \Daycry\Iban\Database\Migrations\CreateIsoCountriesTable
 * @see \Daycry\Iban\Providers\DatabaseIsoCountryLoader
 * @see \Daycry\Iban\Config\Services::isoCountries()
 */
class IsoCountryModel extends Model
{
    protected $table         = 'iso_countries';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = false;

    /** @var list<string> */
    protected $allowedFields = [
        'alpha2',
        'name',
        'alpha3',
        'numeric',
    ];

    /**
     * @param string|null $table   Overrides the queried table (default:
     *                              `'iso_countries'`) when non-null, e.g. from
     *                              `Config\Iban::$isoCountryTable`.
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
}
