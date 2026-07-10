<?php

declare(strict_types=1);

/**
 * Raw IBAN structure registry data, keyed by ISO 3166-1 alpha-2 country code.
 *
 * Independently authored structural facts (lengths / field offsets / BBAN
 * tokens) derived from publicly documented IBAN formats — NOT copied or
 * derived from the SWIFT IBAN Registry file. `branch` and `national_check`
 * may be `null` for countries whose BBAN has no such field.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §6.1
 *
 * @var array<string, array<string, mixed>>
 */
return [
    'ES' => [
        'iban_length'    => 24,
        'bban_structure' => '4!n4!n2!n10!n',
        'bank'           => [4, 4],
        'branch'         => [8, 4],
        'account'        => [14, 10],
        'national_check' => [12, 2],
        'sepa'           => true,
        'example'        => 'ES9121000418450200051332',
    ],
];
