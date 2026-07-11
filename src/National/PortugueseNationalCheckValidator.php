<?php

declare(strict_types=1);

namespace Daycry\Iban\National;

use Daycry\Iban\Contracts\NationalCheckValidatorInterface;
use Daycry\Iban\DTO\ParsedIban;

/**
 * Portuguese (PT) national check digit validator: the Portuguese NIB check
 * digits are a weighted mod-97 over the 19 digits of bank+branch+account
 * (4+4+11), check = 98 - (sum of digit*weight mod 97), collapsing 98 -> 0
 * and 97 -> 1.
 *
 * Verified against the real registry example IBAN
 * PT50000201231234567890154 (nineteen '0002012312345678901', sum=2469,
 * mod=44, 98-44=54, matching the real national check '54'), plus two
 * additional real bank IBANs found during research (both independently
 * MOD-97-valid at the whole-IBAN level too):
 *   - PT50003300000017351398905 (Millennium BCP sample) -> check '05', match.
 *   - PT50003500270069917613010 (Caixa Geral de Depósitos sample) -> check
 *     '10', match.
 *
 * @see .superpowers/sdd/task-v4a-brief.md
 * @see .superpowers/sdd/task-v4a-report.md
 */
final class PortugueseNationalCheckValidator implements NationalCheckValidatorInterface
{
    /** @var int[] */
    private const WEIGHTS = [73, 17, 89, 38, 62, 45, 53, 15, 50, 5, 49, 34, 81, 76, 27, 90, 9, 30, 3];

    public function supports(string $countryCode): bool
    {
        return strtoupper($countryCode) === 'PT';
    }

    public function verify(ParsedIban $iban): bool
    {
        if (!$this->supports($iban->countryCode)) {
            return true; // Not applicable -- the Validator already filters by supports().
        }

        if ($iban->branchIdentifier === null || $iban->nationalCheckDigit === null) {
            return false; // A well-parsed PT IBAN always has both.
        }

        $nineteen = $iban->bankIdentifier . $iban->branchIdentifier . $iban->accountNumber;

        $sum = 0;

        for ($i = 0; $i < 19; $i++) {
            $sum += ((int) $nineteen[$i]) * self::WEIGHTS[$i];
        }

        $check = 98 - ($sum % 97);

        if ($check === 98) {
            $check = 0;
        } elseif ($check === 97) {
            $check = 1;
        }

        return str_pad((string) $check, 2, '0', STR_PAD_LEFT) === $iban->nationalCheckDigit;
    }
}
