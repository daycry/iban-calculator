<?php

declare(strict_types=1);

namespace Daycry\Iban\DTO;

/**
 * Bank information DTO without ParsedIban composition.
 * Output of ProviderInterface implementations.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §4
 */
final readonly class BankInfo
{
    public function __construct(
        public ?string $bankName,
        public ?string $shortName,
        public ?string $bic,
        public ?string $city,
        public ?string $address,
        public ?bool $sepaSct,
        public ?bool $sepaSctInst,
        public ?bool $sepaSddCore,
        public ?bool $sepaSddB2b,
        public ?string $sourceId,
        public ?string $sourceVersion,
        public ?string $sourceLicense,
        /**
         * Identifies WHICH provider produced this data — e.g. `'database'`
         * (local `banks` table), `'iban.com'` (remote API fallback), or a
         * custom provider's own id. Distinct from `$sourceId`, which
         * identifies the DATASET the data came from (`'epc'`, `'bde'`, …).
         * `null` when unknown/unresolved.
         */
        public ?string $resolvedBy = null,
    ) {
    }
}
