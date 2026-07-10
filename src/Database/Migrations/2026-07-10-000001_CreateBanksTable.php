<?php

declare(strict_types=1);

namespace Daycry\Iban\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the `banks` table used by {@see \Daycry\Iban\Models\BankModel} and
 * {@see \Daycry\Iban\Providers\DatabaseProvider}.
 *
 * `$DBGroup` is intentionally left unset so the migration runs against
 * whichever group the migration runner was invoked with (e.g. `tests` under
 * CodeIgniter's testing bootstrap, `default` in a consuming application).
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §7
 */
class CreateBanksTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField('id');

        $this->forge->addField([
            'country_code' => [
                'type'       => 'CHAR',
                'constraint' => 2,
                'null'       => false,
            ],
            'bank_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 35,
                'null'       => false,
            ],
            'branch_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 35,
                'null'       => true,
            ],
            'bic' => [
                'type'       => 'VARCHAR',
                'constraint' => 11,
                'null'       => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'short_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 140,
                'null'       => true,
            ],
            'city' => [
                'type'       => 'VARCHAR',
                'constraint' => 140,
                'null'       => true,
            ],
            'address' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'sepa_sct' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => true,
            ],
            'sepa_sct_inst' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => true,
            ],
            'sepa_sdd_core' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => true,
            ],
            'sepa_sdd_b2b' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => true,
            ],
            'source_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'source_version' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'source_license' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addUniqueKey(['country_code', 'bank_code', 'branch_code']);
        $this->forge->addKey('country_code');
        $this->forge->addKey('bic');

        $this->forge->createTable('banks');
    }

    public function down(): void
    {
        $this->forge->dropTable('banks', true);
    }
}
