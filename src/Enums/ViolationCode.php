<?php

declare(strict_types=1);

namespace Daycry\Iban\Enums;

/**
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §4
 */
enum ViolationCode: string
{
    // IBAN validation (the original 8 cases; do not reorder or renumber).
    case Blank = 'blank';
    case TooShort = 'too_short';
    case UnknownCountry = 'unknown_country';
    case IllegalCharacters = 'illegal_characters';
    case BadLength = 'bad_length';
    case MalformedStructure = 'malformed_structure';
    case ChecksumFailed = 'checksum_failed';
    case NationalCheckFailed = 'national_check_failed';

    // BIC (ISO 9362) validation. Backing values carry a `bic_` prefix so a
    // consumer can tell from the code alone WHICH field failed — a BIC has no
    // checksum, so "valid" here only ever means "well-formed + recognised
    // country", never "this BIC exists on the SWIFT network".
    case BicBlank = 'bic_blank';
    case BicBadLength = 'bic_bad_length';
    case BicIllegalCharacters = 'bic_illegal_characters';
    case BicMalformedStructure = 'bic_malformed_structure';
    case BicUnknownCountry = 'bic_unknown_country';

    // IBAN <-> BIC cross-check (only meaningful when BOTH are supplied and
    // individually valid). The `bic_iban_` prefix marks a coherence failure
    // between the two, not a defect in either one alone.
    case BicIbanCountryMismatch = 'bic_iban_country_mismatch';
    case BicIbanBankMismatch = 'bic_iban_bank_mismatch';

    // Combined IBAN-and-BIC entry point: neither value was supplied, so there
    // was nothing to validate. Not prefixed — it is neither IBAN- nor
    // BIC-specific.
    case NothingToValidate = 'nothing_to_validate';
}
