<?php

declare(strict_types=1);

namespace Daycry\Iban\National;

use Daycry\Iban\Contracts\NationalCheckValidatorInterface;
use Daycry\Iban\DTO\ParsedIban;

/**
 * French (FR) RIB key national check digit validator, also covering Monaco
 * (MC), which shares the exact same BBAN structure (5-digit bank +
 * 5-digit branch + 11-character account + 2-digit RIB key).
 *
 * The RIB ("Relevé d'Identité Bancaire") key is a 2-digit check computed as:
 *
 *   key = 97 - ((89*B + 15*G + 3*C) mod 97)
 *
 * where B = 5-digit bank code, G = 5-digit branch ("guichet") code, and
 * C = the 11-character account number with any letters first converted to
 * a digit via the RIB letter table:
 *
 *   A,J=1  B,K,S=2  C,L,T=3  D,M,U=4  E,N,V=5
 *   F,O,W=6  G,P,X=7  H,Q,Y=8  I,R,Z=9
 *
 * (digits are left unchanged). Because B/G/C can be up to 11 digits long,
 * `89*B + 15*G + 3*C` is never built as a big integer: each component is
 * first reduced mod 97 via a streaming remainder (`rem = (rem*10 + digit)
 * % 97` over each digit in turn), and only the small (0..96) reduced
 * values are combined.
 *
 * Verified against the real registry example IBAN
 * FR1420041010050500013M02606: bank='20041', branch='01005',
 * account='0500013M026' (the 'M' maps to digit 4, giving digit-string
 * '05000134026') -- computed RIB key = 97 - ((89*59 + 15*35 + 3*45) mod 97)
 * = 97 - 91 = 6 -> '06', matching the real national check exactly.
 *
 * MC shares the identical algorithm/structure and was verified against its
 * own registry example MC5811222000010123456789030: bank='11222',
 * branch='00001', account='01234567890' -- computed RIB key = '30',
 * matching the real national check exactly.
 *
 * @see .superpowers/sdd/task-v4b-brief.md
 * @see .superpowers/sdd/task-v4b-report.md
 */
final class FrenchNationalCheckValidator implements NationalCheckValidatorInterface
{
    private const MOD = 97;

    /** @var array<string, int> RIB account letter -> digit table. */
    private const LETTER_TABLE = [
        'A' => 1, 'J' => 1,
        'B' => 2, 'K' => 2, 'S' => 2,
        'C' => 3, 'L' => 3, 'T' => 3,
        'D' => 4, 'M' => 4, 'U' => 4,
        'E' => 5, 'N' => 5, 'V' => 5,
        'F' => 6, 'O' => 6, 'W' => 6,
        'G' => 7, 'P' => 7, 'X' => 7,
        'H' => 8, 'Q' => 8, 'Y' => 8,
        'I' => 9, 'R' => 9, 'Z' => 9,
    ];

    public function supports(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), ['FR', 'MC'], true);
    }

    public function verify(ParsedIban $iban): bool
    {
        if (!$this->supports($iban->countryCode)) {
            return true; // Not applicable -- the Validator already filters by supports().
        }

        if ($iban->branchIdentifier === null || $iban->nationalCheckDigit === null) {
            return false; // A well-parsed FR/MC IBAN always has both.
        }

        $accountDigits = $this->toDigitString($iban->accountNumber);

        $bMod = $this->streamMod($iban->bankIdentifier);
        $gMod = $this->streamMod($iban->branchIdentifier);
        $cMod = $this->streamMod($accountDigits);

        $sum = (89 * $bMod + 15 * $gMod + 3 * $cMod) % self::MOD;
        $key = self::MOD - $sum;

        return str_pad((string) $key, 2, '0', STR_PAD_LEFT) === $iban->nationalCheckDigit;
    }

    /**
     * Converts an alphanumeric RIB account string into its all-digit form:
     * digits are kept as-is, letters are mapped via the RIB letter table.
     */
    private function toDigitString(string $account): string
    {
        $out = '';
        $len = strlen($account);

        for ($i = 0; $i < $len; $i++) {
            $ch  = strtoupper($account[$i]);
            $out .= ctype_digit($ch) ? $ch : (string) (self::LETTER_TABLE[$ch] ?? 0);
        }

        return $out;
    }

    /**
     * Reduces a string of decimal digits mod 97 without ever building a big
     * integer: streams digit by digit, carrying only the (0..96) remainder
     * forward (`rem = (rem*10 + digit) % 97`).
     */
    private function streamMod(string $digits): int
    {
        $rem = 0;
        $len = strlen($digits);

        for ($i = 0; $i < $len; $i++) {
            $rem = ($rem * 10 + (int) $digits[$i]) % self::MOD;
        }

        return $rem;
    }
}
