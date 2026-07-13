<?php

declare(strict_types=1);

namespace Daycry\Iban\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the `iso_countries` table used by the optional database-backed
 * ISO 3166-1 source ({@see \Daycry\Iban\Models\IsoCountryModel} /
 * {@see \Daycry\Iban\Providers\DatabaseIsoCountryLoader}).
 *
 * This table is OPTIONAL: the package's default ISO 3166-1 source is the
 * compiled `src/Registry/data/iso_countries.php` list, which needs no database
 * at all. Run this migration (and {@see \Daycry\Iban\Database\Seeds\IsoCountriesSeeder})
 * only when `Config\Iban::$isoCountrySource` is set to `'database'`.
 *
 * `$DBGroup` is intentionally left unset so the migration runs against
 * whichever group the migration runner was invoked with (e.g. `tests` under
 * CodeIgniter's testing bootstrap, `default` in a consuming application).
 *
 * The `numeric` column is a reserved word in several SQL dialects; it is only
 * ever referenced through CodeIgniter's Forge/Query Builder, which escapes
 * identifiers, so it is stored and queried safely.
 */
class CreateIsoCountriesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField('id');

        $this->forge->addField([
            'alpha2' => [
                'type'       => 'CHAR',
                'constraint' => 2,
                'null'       => false,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
            ],
            'alpha3' => [
                'type'       => 'CHAR',
                'constraint' => 3,
                'null'       => true,
            ],
            'numeric' => [
                'type'       => 'CHAR',
                'constraint' => 3,
                'null'       => true,
            ],
        ]);

        $this->forge->addUniqueKey('alpha2');

        $this->forge->createTable('iso_countries');
    }

    public function down(): void
    {
        $this->forge->dropTable('iso_countries', true);
    }
}
