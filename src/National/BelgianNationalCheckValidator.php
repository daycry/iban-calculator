<?php

declare(strict_types=1);

namespace Daycry\Iban\National;

use Daycry\Iban\Contracts\NationalCheckValidatorInterface;
use Daycry\Iban\DTO\ParsedIban;

/**
 * Belgian (BE) national check digit validator: the 2-digit national check
 * is the first 10 BBAN digits (3-digit bank + 7-digit account) taken as an
 * integer, mod 97 -- with a 0 remainder mapped to 97 (never 00), zero-padded
 * to 2 digits.
 *
 * Verified against the real registry example IBAN BE68539007547034
 * (bank '539', account '0075470', first10 '5390075470', 5390075470 % 97 =
 * 34, so the expected national check is '34', matching the real IBAN).
 *
 * @see .superpowers/sdd/task-v4a-brief.md
 * @see .superpowers/sdd/task-v4a-report.md
 */
final class BelgianNationalCheckValidator implements NationalCheckValidatorInterface
{
    public function supports(string $countryCode): bool
    {
        return strtoupper($countryCode) === 'BE';
    }

    public function verify(ParsedIban $iban): bool
    {
        if (!$this->supports($iban->countryCode)) {
            return true; // Not applicable -- the Validator already filters by supports().
        }

        if ($iban->nationalCheckDigit === null) {
            return false; // A well-parsed BE IBAN always has one.
        }

        $first10 = $iban->bankIdentifier . $iban->accountNumber;
        $mod     = ((int) $first10) % 97;
        $expected = $mod === 0 ? 97 : $mod;

        return str_pad((string) $expected, 2, '0', STR_PAD_LEFT) === $iban->nationalCheckDigit;
    }
}
