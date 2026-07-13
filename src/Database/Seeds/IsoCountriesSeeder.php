<?php

declare(strict_types=1);

namespace Daycry\Iban\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Daycry\Iban\Registry\PhpIsoCountryLoader;

/**
 * Populates the `iso_countries` table from the compiled ISO 3166-1 list
 * ({@see PhpIsoCountryLoader}), so an operator can switch
 * `Config\Iban::$isoCountrySource` to `'database'` and read the same data the
 * standalone/compiled path already ships.
 *
 * Run it with:
 *
 *     php spark db:seed "Daycry\\Iban\\Database\\Seeds\\IsoCountriesSeeder"
 *
 * The insert is a single BATCH upsert keyed on the unique `alpha2` column, so
 * running the seeder repeatedly refreshes each row in place instead of
 * inserting duplicates — it is safe to re-run after the compiled list is
 * refreshed. Requires the `CreateIsoCountriesTable` migration to have run
 * first.
 *
 * @see \Daycry\Iban\Database\Migrations\CreateIsoCountriesTable
 * @see \Daycry\Iban\Providers\DatabaseIsoCountryLoader
 */
class IsoCountriesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [];

        foreach ((new PhpIsoCountryLoader())->load() as $alpha2 => $data) {
            $rows[] = [
                'alpha2'  => $alpha2,
                'name'    => $data['name'],
                'alpha3'  => $data['alpha3'],
                'numeric' => $data['numeric'],
            ];
        }

        if ($rows === []) {
            return;
        }

        $this->db->table('iso_countries')
            ->onConstraint('alpha2')
            ->updateFields(['name', 'alpha3', 'numeric'])
            ->upsertBatch($rows, null, count($rows));
    }
}
