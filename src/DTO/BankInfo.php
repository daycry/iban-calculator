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
    ) {
    }
}
