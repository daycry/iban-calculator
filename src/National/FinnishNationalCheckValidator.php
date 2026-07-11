<?php

declare(strict_types=1);

namespace Daycry\Iban\National;

use Daycry\Iban\Contracts\NationalCheckValidatorInterface;
use Daycry\Iban\DTO\ParsedIban;

/**
 * Finnish (FI) national check digit validator: the final BBAN digit is a
 * Luhn (mod-10) check digit computed over the preceding 13 digits of
 * bank+account (3+10). The Luhn algorithm is applied right-to-left,
 * doubling every second digit (subtracting 9 when the double exceeds 9),
 * summing all digits, with check = (10 - (sum mod 10)) mod 10.
 *
 * Being right-anchored, Luhn is invariant to left zero-padding, so it is
 * safe to compute directly over the fixed-width bank+account fields as
 * sliced by the registry, regardless of the true (bank-specific, variable)
 * length of the underlying domestic account number.
 *
 * Verified against the real registry example IBAN FI2112345600000785
 * (bank '123', account '4560000078', thirteen '1234560000078', Luhn
 * check=5, matching the real national check '5'), plus three additional
 * real Nordea Finland test-account IBANs found during research:
 *   - FI4819503000000010 -> Luhn check=0, match.
 *   - FI0819503000000051 -> Luhn check=1, match.
 *   - FI8319503000004327 -> Luhn check=7, match.
 *
 * @see .superpowers/sdd/task-v4a-brief.md
 * @see .superpowers/sdd/task-v4a-report.md
 */
final class FinnishNationalCheckValidator implements NationalCheckValidatorInterface
{
    public function supports(string $countryCode): bool
    {
        return strtoupper($countryCode) === 'FI';
    }

    public function verify(ParsedIban $iban): bool
    {
        if (!$this->supports($iban->countryCode)) {
            return true; // Not applicable -- the Validator already filters by supports().
        }

        if ($iban->nationalCheckDigit === null) {
            return false; // A well-parsed FI IBAN always has one.
        }

        $thirteen = $iban->bankIdentifier . $iban->accountNumber;
        $sum      = 0;

        // Right-to-left, doubling every second digit starting from the rightmost.
        for ($i = strlen($thirteen) - 1, $double = true; $i >= 0; $i--, $double = !$double) {
            $digit = (int) $thirteen[$i];

            if ($double) {
                $digit *= 2;

                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        $check = (10 - ($sum % 10)) % 10;

        return (string) $check === $iban->nationalCheckDigit;
    }
}
