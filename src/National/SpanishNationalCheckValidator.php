<?php

declare(strict_types=1);

namespace Daycry\Iban\National;

use Daycry\Iban\Contracts\NationalCheckValidatorInterface;
use Daycry\Iban\DTO\ParsedIban;

/**
 * Spanish (ES) national check digit validator: weighted mod-11 over the
 * two national check digits (DC1 over bank+branch, DC2 over account).
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 * @see .superpowers/sdd/task-26-brief.md
 */
final class SpanishNationalCheckValidator implements NationalCheckValidatorInterface
{
    /** @var int[] */
    private const WEIGHTS = [1, 2, 4, 8, 5, 10, 9, 7, 3, 6];

    public function supports(string $countryCode): bool
    {
        return strtoupper($countryCode) === 'ES';
    }

    public function verify(ParsedIban $iban): bool
    {
        if (!$this->supports($iban->countryCode)) {
            return true; // Not applicable -- the Validator already filters by supports().
        }

        if ($iban->branchIdentifier === null || $iban->nationalCheckDigit === null) {
            return false; // A well-parsed ES IBAN always has both.
        }

        $dc1 = $this->controlDigit('00' . $iban->bankIdentifier . $iban->branchIdentifier);
        $dc2 = $this->controlDigit($iban->accountNumber);

        return ($dc1 . $dc2) === $iban->nationalCheckDigit;
    }

    /**
     * Compute a single weighted mod-11 control digit over 10 digits.
     */
    private function controlDigit(string $tenDigits): string
    {
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $sum += ((int) $tenDigits[$i]) * self::WEIGHTS[$i];
        }

        $r = 11 - ($sum % 11);

        if ($r === 10) {
            return '1';
        }

        if ($r === 11) {
            return '0';
        }

        return (string) $r;
    }
}
