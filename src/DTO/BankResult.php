<?php

declare(strict_types=1);

namespace Daycry\Iban\DTO;

/**
 * Bank resolution result: ParsedIban + bank data.
 * Output of Resolver::resolve().
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §4
 */
final readonly class BankResult
{
    public function __construct(
        public ParsedIban $iban,
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

    /**
     * Returns true if any of the 12 bank fields is non-null.
     */
    public function isResolved(): bool
    {
        return $this->bankName !== null
            || $this->shortName !== null
            || $this->bic !== null
            || $this->city !== null
            || $this->address !== null
            || $this->sepaSct !== null
            || $this->sepaSctInst !== null
            || $this->sepaSddCore !== null
            || $this->sepaSddB2b !== null
            || $this->sourceId !== null
            || $this->sourceVersion !== null
            || $this->sourceLicense !== null;
    }
}
