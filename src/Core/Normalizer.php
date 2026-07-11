<?php

declare(strict_types=1);

namespace Daycry\Iban\Core;

/**
 * Canonicalizes raw IBAN input before any validation, parsing or formatting.
 *
 * Normalization is a purely mechanical, non-judgmental step: it uppercases
 * the input, strips all whitespace, and drops a leading "IBAN" prefix
 * (with any trailing non-alphanumeric separator, e.g. "IBAN:"). It does
 * NOT validate the result nor strip/replace invalid characters — those are
 * preserved so the Validator can report them (e.g. IllegalCharacters).
 *
 * Applying checks before normalization (e.g. before uppercasing) is the
 * classic ordering bug this class is meant to prevent.
 */
final class Normalizer
{
    public function normalize(string $iban): string
    {
        $normalized = strtoupper($iban);
        $normalized = (string) preg_replace('/\s+/', '', $normalized);

        return (string) preg_replace('/^IBAN[^A-Z0-9]*/', '', $normalized);
    }
}
