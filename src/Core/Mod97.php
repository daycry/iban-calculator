<?php

declare(strict_types=1);

namespace Daycry\Iban\Core;

/**
 * MOD-97-10 (ISO 7064) check-digit engine used by IBAN validation and generation.
 *
 * Implemented as a "windowed" modulo (see Apache Commons `IBANCheckDigit`):
 * the numeric representation of the rearranged IBAN can be up to ~34 digits
 * (letters expand to two digits each), which overflows a 64-bit integer.
 * Instead of casting the full numeric string to int, the string is processed
 * in 7-digit blocks, carrying the (at most 2-digit) remainder in front of
 * the next block. Each intermediate block therefore never exceeds 9 digits,
 * which safely fits in a 64-bit int.
 */
final class Mod97
{
    public function isValid(string $normalizedIban): bool
    {
        if (strlen($normalizedIban) < 4) {
            return false;
        }

        $rearranged = substr($normalizedIban, 4) . substr($normalizedIban, 0, 4);

        return $this->mod97($this->toNumericString($rearranged)) === 1;
    }

    public function checkDigits(string $countryCode, string $bban): string
    {
        $rearranged = $bban . $countryCode . '00';
        $mod        = $this->mod97($this->toNumericString($rearranged));
        $check      = 98 - $mod;

        return str_pad((string) $check, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Converts a rearranged IBAN/BBAN string (digits and A-Z letters) into
     * its numeric string form: digits are kept as-is, letters A-Z map to
     * 10-35 (A=10, ..., Z=35) per ISO 13616 / ISO 7064.
     *
     * The input is expected to already be restricted to [A-Z0-9] by the
     * Validator; any other character is mapped the same way as a letter
     * would be out of its A-Z range, which is a defensive fallback only.
     */
    private function toNumericString(string $rearranged): string
    {
        $out = '';
        $len = strlen($rearranged);

        for ($i = 0; $i < $len; $i++) {
            $ch = $rearranged[$i];

            if ($ch >= '0' && $ch <= '9') {
                $out .= $ch;
            } else {
                $out .= (string) (ord($ch) - 55);
            }
        }

        return $out;
    }

    /**
     * Windowed MOD-97: processes the numeric string in 7-digit blocks,
     * carrying the remainder (<= 2 digits) in front of the next block.
     * Each block is at most 9 digits, safely within 64-bit int range.
     */
    private function mod97(string $numeric): int
    {
        $remainder = 0;
        $length    = strlen($numeric);

        for ($offset = 0; $offset < $length; $offset += 7) {
            $block     = ($remainder === 0 ? '' : (string) $remainder) . substr($numeric, $offset, 7);
            $remainder = ((int) $block) % 97;
        }

        return $remainder;
    }
}
