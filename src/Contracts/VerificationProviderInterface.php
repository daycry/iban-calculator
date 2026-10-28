<?php

declare(strict_types=1);

namespace Daycry\Iban\Contracts;

use Daycry\Iban\DTO\ExternalVerification;

/** Optional external checks; inputs are normalized electronic values. */
interface VerificationProviderInterface
{
    /** Validates the identifier, without claiming that the customer's account exists. */
    public function verifyIban(string $iban): ExternalVerification;

    /** Checks a BIC directory; existence and network connectivity are separate facts. */
    public function verifyBic(string $bic): ExternalVerification;
}
