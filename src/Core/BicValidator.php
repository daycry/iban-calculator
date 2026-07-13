<?php

declare(strict_types=1);

namespace Daycry\Iban\Core;

use Daycry\Iban\DTO\ParsedBic;
use Daycry\Iban\DTO\ValidationResult;
use Daycry\Iban\DTO\Violation;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Registry\IsoCountryRegistry;

/**
 * ISO 9362 (BIC / SWIFT code) well-formedness validator.
 *
 * Mirrors {@see Validator}'s check-ordering style: it normalizes the input
 * (unless already a {@see ParsedBic}) and runs a fixed, short-circuiting
 * sequence of checks, returning on the FIRST violation:
 *
 *   1. BicBlank             — empty after normalization
 *   2. BicBadLength         — length is not exactly 8 or 11
 *   3. BicIllegalCharacters — a character outside `[A-Z0-9]`
 *   4. BicMalformedStructure — right length/charset, wrong char class per position
 *   5. BicUnknownCountry    — positions 5-6 not an ISO 3166-1 alpha-2 code
 *
 * `validate()`/`isValid()` NEVER throw, even on garbage input.
 *
 * IMPORTANT — a BIC has NO checksum (unlike an IBAN's MOD-97 check digits).
 * A "valid" result therefore only means "well-formed AND its country code is
 * recognised"; it can NEVER mean "this BIC exists / is live on the SWIFT
 * network". Confirming that a BIC is real requires a directory lookup (e.g.
 * SWIFTRef), which this offline validator deliberately does not attempt.
 *
 * Structure rule (the ISO 20022 / EPC / SEPA canonical pattern), applied after
 * normalization:
 *
 *   ^[A-Z]{6}[A-Z2-9][A-NP-Z0-9]([A-Z0-9]{3})?$
 *
 *   - positions 1-6  institution code (4) + country code (2): letters only
 *   - position 7     location char 1: `[A-Z2-9]` (digits 0 and 1 are illegal here)
 *   - position 8     location char 2: `[A-NP-Z0-9]` (letter `O` excluded; digits
 *                    0/1/2 are allowed and semantically meaningful — 0 = test,
 *                    1 = passive participant, 2 = reverse billing — so they must
 *                    NOT be rejected)
 *   - positions 9-11 optional branch code: `[A-Z0-9]{3}`
 *
 * Country-code policy (position 5-6): checked against the FULL ISO 3166-1
 * alpha-2 set via {@see IsoCountryRegistry} (~249 countries), NOT the ~78-country
 * IBAN registry — most countries with BICs (US, JP, CN, …) issue no IBAN, so
 * reusing the IBAN registry would wrongly reject the majority of real BICs.
 * The bundled ISO registry contains only OFFICIALLY assigned alpha-2 codes; the
 * user-assigned code `XK` (Kosovo) is DELIBERATELY excluded there but IS used in
 * real BICs, so this validator layers `XK` on explicitly (see {@see EXTRA_COUNTRY_CODES}).
 * No other non-ISO code (`UK`, `EU`, `EL`, …) had evidence of genuine BIC use, so
 * none are added.
 */
final class BicValidator
{
    /**
     * Canonical ISO 20022 / EPC BIC structure pattern (see class docblock).
     */
    private const STRUCTURE_PATTERN = '/^[A-Z]{6}[A-Z2-9][A-NP-Z0-9]([A-Z0-9]{3})?$/';

    /**
     * Country codes accepted for a BIC beyond the officially assigned ISO
     * 3166-1 alpha-2 set: `XK` (Kosovo) is a user-assigned code used as a
     * de-facto country code by SWIFT/IMF/EC, and real XK BICs exist.
     *
     * @var list<string>
     */
    private const EXTRA_COUNTRY_CODES = ['XK'];

    public function __construct(
        private IsoCountryRegistry $isoCountries = new IsoCountryRegistry(),
    ) {
    }

    /**
     * Canonicalize raw BIC input: strip all whitespace and uppercase.
     *
     * Purely mechanical — it does NOT drop or replace invalid characters, so
     * {@see validate()} can still report them (BicIllegalCharacters).
     */
    public function normalize(string $bic): string
    {
        return strtoupper((string) preg_replace('/\s+/', '', $bic));
    }

    public function validate(string|ParsedBic $bic): ValidationResult
    {
        $normalized = $bic instanceof ParsedBic ? $bic->bic : $this->normalize($bic);

        if ($normalized === '') {
            return $this->violation(
                ViolationCode::BicBlank,
                'bic.violation.blank',
                'The BIC is empty.'
            );
        }

        $length = strlen($normalized);

        if ($length !== 8 && $length !== 11) {
            return $this->violation(
                ViolationCode::BicBadLength,
                'bic.violation.bad_length',
                'The BIC must be 8 or 11 characters long.'
            );
        }

        if (preg_match('/[^A-Z0-9]/', $normalized) === 1) {
            return $this->violation(
                ViolationCode::BicIllegalCharacters,
                'bic.violation.illegal_characters',
                'The BIC contains illegal characters.'
            );
        }

        if (preg_match(self::STRUCTURE_PATTERN, $normalized) !== 1) {
            return $this->violation(
                ViolationCode::BicMalformedStructure,
                'bic.violation.malformed_structure',
                'The BIC structure is malformed.'
            );
        }

        $countryCode = substr($normalized, 4, 2);

        if (!$this->isoCountries->has($countryCode) && !in_array($countryCode, self::EXTRA_COUNTRY_CODES, true)) {
            return $this->violation(
                ViolationCode::BicUnknownCountry,
                'bic.violation.unknown_country',
                'Unknown or unsupported BIC country code.'
            );
        }

        return new ValidationResult(true, []);
    }

    public function isValid(string|ParsedBic $bic): bool
    {
        return $this->validate($bic)->isValid();
    }

    /**
     * Slice an already-valid, normalized BIC into its structural fields.
     *
     * Assumes `$normalized` has already passed {@see validate()} (8 or 11
     * chars, correct structure). Reused by {@see BicParser}.
     */
    public function toParsedBic(string $normalized): ParsedBic
    {
        $branch = strlen($normalized) === 11 ? substr($normalized, 8, 3) : null;

        return new ParsedBic(
            bic: $normalized,
            institutionCode: substr($normalized, 0, 4),
            countryCode: substr($normalized, 4, 2),
            locationCode: substr($normalized, 6, 2),
            branchCode: $branch,
        );
    }

    private function violation(ViolationCode $code, string $messageKey, string $message): ValidationResult
    {
        return new ValidationResult(false, [new Violation($code, $messageKey, $message)]);
    }
}
