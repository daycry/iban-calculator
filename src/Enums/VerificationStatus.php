<?php

declare(strict_types=1);

namespace Daycry\Iban\Enums;

enum VerificationStatus: string
{
    case FormatValid = 'format_valid';
    case FormatInvalid = 'format_invalid';
    case UnsupportedCountry = 'unsupported_country';
    case ExternallyValidated = 'externally_validated';
    case ExternalRejected = 'external_rejected';
    case VerificationUnavailable = 'verification_unavailable';
}
