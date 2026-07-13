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
        /**
         * Identifies WHICH provider produced this data — e.g. `'database'`
         * (local `banks` table), `'iban.com'` (remote API fallback), or a
         * custom provider's own id. Distinct from `$sourceId`, which
         * identifies the DATASET the data came from (`'epc'`, `'bde'`, …).
         * `null` when unknown/unresolved.
         *
         * METADATA ONLY: deliberately excluded from {@see self::isResolved()},
         * which must keep reflecting only the presence of actual bank data.
         */
        public ?string $resolvedBy = null,
    ) {
    }

    /**
     * Returns true if any of the 12 bank fields is non-null.
     *
     * `$resolvedBy` is provenance metadata, not bank data, and is
     * deliberately excluded from this check — a `BankResult` with only
     * `$resolvedBy` set (and every bank field null) must still report
     * `isResolved() === false`.
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
