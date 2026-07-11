<?php

declare(strict_types=1);

namespace Daycry\Iban\National;

use Daycry\Iban\Contracts\NationalCheckValidatorInterface;
use Daycry\Iban\DTO\ParsedIban;

/**
 * Italian (IT) CIN ("Carattere di Controllo Interno") national check letter
 * validator, also covering San Marino (SM), which shares the exact same
 * BBAN structure (1-letter CIN + 5-digit ABI/bank + 5-digit CAB/branch +
 * 12-character account).
 *
 * The CIN is computed over the 22 BBAN characters that follow it (ABI + CAB
 * + account, 5+5+12 = 22 chars): each character is assigned a value from an
 * ODD or EVEN table depending on its 1-indexed position within that
 * 22-character tail (odd positions use the ODD table, even positions use
 * the EVEN table); the 22 values are summed and `sum mod 26` selects a
 * letter (0 => A, ..., 25 => Z):
 *
 *   EVEN: digits 0-9 keep their value; letters A-Z map to 0-25.
 *   ODD:  0=1 1=0 2=5 3=7 4=9 5=13 6=15 7=17 8=19 9=21
 *         A=1 B=0 C=5 D=7 E=9 F=13 G=15 H=17 I=19 J=21
 *         K=2 L=4 M=18 N=20 O=11 P=3 Q=6 R=8 S=12 T=14
 *         U=16 V=10 W=22 X=25 Y=24 Z=23
 *
 * Verified against the real registry example IBAN
 * IT60X0542811101000000123456: CIN='X', 22-char tail
 * '0542811101000000123456' -- sum mod 26 = 23 -> 'X', matching the real
 * national check letter exactly.
 *
 * SM shares the identical algorithm/structure and was verified against its
 * own registry example SM86U0322509800000000270100: CIN='U', 22-char tail
 * '0322509800000000270100' -- sum mod 26 = 20 -> 'U', matching the real
 * national check letter exactly.
 *
 * @see .superpowers/sdd/task-v4b-brief.md
 * @see .superpowers/sdd/task-v4b-report.md
 */
final class ItalianNationalCheckValidator implements NationalCheckValidatorInterface
{
    private const ALPHABET_SIZE = 26;

    /**
     * ODD-position char -> value table. Numeric-string keys ('0'..'9') are
     * coerced to int keys by PHP's array literal syntax, hence the mixed
     * int|string key type.
     *
     * @var array<int|string, int>
     */
    private const ODD_TABLE = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9,
        '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9,
        'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11,
        'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    public function supports(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), ['IT', 'SM'], true);
    }

    public function verify(ParsedIban $iban): bool
    {
        if (!$this->supports($iban->countryCode)) {
            return true; // Not applicable -- the Validator already filters by supports().
        }

        if ($iban->branchIdentifier === null || $iban->nationalCheckDigit === null) {
            return false; // A well-parsed IT/SM IBAN always has both.
        }

        $tail = strtoupper($iban->bankIdentifier . $iban->branchIdentifier . $iban->accountNumber);
        $len  = strlen($tail);

        $sum = 0;

        for ($i = 0; $i < $len; $i++) {
            $position = $i + 1; // 1-indexed position within the 22-char tail
            $ch       = $tail[$i];

            $sum += $position % 2 === 1 ? (self::ODD_TABLE[$ch] ?? 0) : $this->evenValue($ch);
        }

        $letter = chr(ord('A') + ($sum % self::ALPHABET_SIZE));

        return $letter === strtoupper($iban->nationalCheckDigit);
    }

    /**
     * EVEN-position value: digits keep their numeric value (0-9); letters
     * A-Z map to 0-25.
     */
    private function evenValue(string $ch): int
    {
        return ctype_digit($ch) ? (int) $ch : ord($ch) - ord('A');
    }
}
