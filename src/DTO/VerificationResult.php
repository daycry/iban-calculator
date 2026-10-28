<?php

declare(strict_types=1);

namespace Daycry\Iban\DTO;

use Daycry\Iban\Enums\VerificationStatus;
use Daycry\Iban\Enums\ViolationCode;

final readonly class VerificationResult
{
    public VerificationStatus $status;

    public function __construct(
        public ValidationResult $validation,
        public ?ExternalVerification $external = null,
    ) {
        $this->status = match (true) {
            $validation->firstViolation()?->code === ViolationCode::UnknownCountry => VerificationStatus::UnsupportedCountry,
            !$validation->isValid() => VerificationStatus::FormatInvalid,
            $external?->valid === true => VerificationStatus::ExternallyValidated,
            $external?->valid === false => VerificationStatus::ExternalRejected,
            $external !== null => VerificationStatus::VerificationUnavailable,
            default => VerificationStatus::FormatValid,
        };
    }
}
