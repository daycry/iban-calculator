<?php

declare(strict_types=1);

namespace Daycry\Iban\DTO;

/**
 * Structural breakdown of a well-formed ISO 9362 BIC (a.k.a. SWIFT code).
 *
 * Mirrors {@see ParsedIban}'s style: a pure, `final readonly` data holder,
 * framework-free so it stays usable outside CodeIgniter. Produced by
 * {@see \Daycry\Iban\Core\BicParser} only for input that already passed
 * {@see \Daycry\Iban\Core\BicValidator}, so every field is guaranteed to be
 * present and normalized (uppercase, whitespace-stripped).
 *
 * A BIC carries NO checksum (unlike an IBAN's MOD-97 check digits), so a
 * parsed BIC means "well-formed and its country code is recognised" — never
 * "this BIC exists / is live on the SWIFT network". Confirming existence
 * requires a directory lookup.
 *
 * Field layout (1-indexed positions within the 8- or 11-char BIC):
 *   - 1-4  institution / business-party code (alphanumeric)
 *   - 5-6  ISO 3166-1 alpha-2 country code (letters only)
 *   - 7-8  location code
 *   - 9-11 branch code (present only in an 11-char BIC)
 */
final readonly class ParsedBic
{
    /**
     * @param string      $bic             Normalized BIC (uppercase, no spaces); 8 or 11 chars.
     * @param string      $institutionCode Positions 1-4 (bank / business-party code).
     * @param string      $countryCode     Positions 5-6 (ISO 3166-1 alpha-2).
     * @param string      $locationCode    Positions 7-8.
     * @param string|null $branchCode      Positions 9-11, or null when the BIC is 8 chars.
     */
    public function __construct(
        public string $bic,
        public string $institutionCode,
        public string $countryCode,
        public string $locationCode,
        public ?string $branchCode,
    ) {
    }

    /**
     * Whether this BIC denotes the institution's primary (head) office.
     *
     * True for an 8-char BIC (no branch segment) and for an 11-char BIC whose
     * branch code is the reserved `XXX`. An 8-char BIC and the same BIC with a
     * trailing `XXX` therefore denote the same head office.
     */
    public function isPrimaryOffice(): bool
    {
        return $this->branchCode === null || $this->branchCode === 'XXX';
    }

    /**
     * The 8-character head-office form of this BIC (its first 8 characters).
     */
    public function bic8(): string
    {
        return substr($this->bic, 0, 8);
    }

    public function __toString(): string
    {
        return $this->bic;
    }
}
