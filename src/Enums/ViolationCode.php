<?php

declare(strict_types=1);

namespace Daycry\Iban\Enums;

/**
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §4
 */
enum ViolationCode: string
{
    case Blank = 'blank';
    case TooShort = 'too_short';
    case UnknownCountry = 'unknown_country';
    case IllegalCharacters = 'illegal_characters';
    case BadLength = 'bad_length';
    case MalformedStructure = 'malformed_structure';
    case ChecksumFailed = 'checksum_failed';
    case NationalCheckFailed = 'national_check_failed';
}
