<?php

declare(strict_types=1);

namespace Daycry\Iban\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Intentionally empty: this package ships no bundled bank data.
 *
 * The `banks` table is populated by consuming applications (or a future
 * registry-import command), not by this library's own seeder. Its role here
 * is to exist as the canonical seed hook for the `banks` table so consumers
 * (and package tests) have a stable class to `call()`.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §7
 */
class BanksSeeder extends Seeder
{
    public function run(): void
    {
        // No rows inserted by design.
    }
}
