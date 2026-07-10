<?php

declare(strict_types=1);

namespace Daycry\Iban\Contracts;

use Daycry\Iban\DTO\ParsedIban;

/**
 * NationalCheckValidator contract for country-specific check digit validation.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
interface NationalCheckValidatorInterface
{
    /**
     * Check if this validator supports the given country code.
     *
     * @param string $countryCode The ISO 3166-1 alpha-2 country code.
     *
     * @return bool True if this validator supports the country, false otherwise.
     */
    public function supports(string $countryCode): bool;

    /**
     * Verify the national check digits of the IBAN.
     *
     * Absence of implementation is treated as a skip, never a failure.
     *
     * @param ParsedIban $iban The parsed IBAN object.
     *
     * @return bool True if the check digits are valid, false otherwise.
     */
    public function verify(ParsedIban $iban): bool;
}
