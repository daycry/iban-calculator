<?php

declare(strict_types=1);

namespace Daycry\Iban\National;

use Daycry\Iban\Contracts\NationalCheckValidatorInterface;
use Daycry\Iban\DTO\ParsedIban;

/**
 * Slovenian (SI) national check digit validator: an ISO 7064 MOD 97-10
 * style check over the 13 digits of bank+account (5+8), computed the same
 * way as the whole-IBAN MOD-97 check digit: check = 98 - (thirteen-digit
 * number * 100 mod 97), i.e. mod-97 of the 13 digits with "00" appended.
 *
 * Verified against the real registry example IBAN SI56263300012039086
 * (bank '26330', account '00012039', thirteen '2633000120390', *100 mod 97
 * = 12, 98-12=86, matching the real national check '86'), plus two
 * additional real bank IBANs found during research (both independently
 * MOD-97-valid at the whole-IBAN level too):
 *   - SI56191000000123438 -> check '38', match.
 *   - SI56029000000200020 (NLB sample) -> check '20', match.
 *
 * @see .superpowers/sdd/task-v4a-brief.md
 * @see .superpowers/sdd/task-v4a-report.md
 */
final class SlovenianNationalCheckValidator implements NationalCheckValidatorInterface
{
    public function supports(string $countryCode): bool
    {
        return strtoupper($countryCode) === 'SI';
    }

    public function verify(ParsedIban $iban): bool
    {
        if (!$this->supports($iban->countryCode)) {
            return true; // Not applicable -- the Validator already filters by supports().
        }

        if ($iban->nationalCheckDigit === null) {
            return false; // A well-parsed SI IBAN always has one.
        }

        $thirteen = $iban->bankIdentifier . $iban->accountNumber;
        $mod      = ((int) $thirteen * 100) % 97;
        $check    = 98 - $mod;

        return str_pad((string) $check, 2, '0', STR_PAD_LEFT) === $iban->nationalCheckDigit;
    }
}
