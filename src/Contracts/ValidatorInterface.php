<?php

declare(strict_types=1);

namespace Daycry\Iban\Contracts;

use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\DTO\ValidationResult;

/**
 * Validator contract for IBAN validation operations.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
interface ValidatorInterface
{
    /**
     * Validate an IBAN and return a ValidationResult.
     *
     * Never throws an exception; violations are returned in the result.
     *
     * @param string|ParsedIban $iban         The IBAN to validate (string or parsed object).
     * @param bool              $checkNational Whether to also validate national check digits.
     *
     * @return ValidationResult The validation result with any violations.
     */
    public function validate(string|ParsedIban $iban, bool $checkNational = false): ValidationResult;

    /**
     * Quick check if an IBAN is valid.
     *
     * @param string|ParsedIban $iban The IBAN to check.
     *
     * @return bool True if valid, false otherwise.
     */
    public function isValid(string|ParsedIban $iban): bool;
}
