<?php

declare(strict_types=1);

namespace Daycry\Iban\Core;

/** Shared IBAN/BIC whitespace handling for values pasted from PDF/Office documents. */
final class WhitespaceNormalizer
{
    public static function strip(string $value): string
    {
        // Keep malformed UTF-8 intact for validation: a failed /u replacement must never empty it.
        $ascii = (string) preg_replace('/\s+/', '', $value);

        return preg_replace('/[\x{00A0}\x{2000}-\x{200A}\x{202F}\x{3000}\x{200B}\x{FEFF}]+/u', '', $ascii) ?? $ascii;
    }
}
