<?php

declare(strict_types=1);

namespace Daycry\Iban\Core;

/**
 * Compiles a SWIFT `bbanStructure` token string (e.g. `4!n4!n2!n10!n`) into
 * an anchored regular expression that validates the charset and length of a
 * BBAN, one token at a time.
 *
 * Token grammar: `<length><!?><class>`, where `class` is one of:
 *   - `n` digits            → `[0-9]`
 *   - `a` upper-case letters → `[A-Z]`
 *   - `c` alphanumeric       → `[A-Z0-9]` (validated AFTER upper-casing, in
 *                              line with {@see Normalizer})
 *   - `e` literal space      → ` ` (escaped)
 * A trailing `!` means the length is fixed (`{length}`); its absence means
 * the length is variable, from 1 up to `length` (`{1,length}`).
 *
 * Compiled regexes are cached per `bbanStructure` string so repeated lookups
 * (e.g. validating many IBANs of the same country) do not re-parse tokens.
 *
 * This class only validates the *shape* (charset + length per token) of a
 * BBAN; it does not check IBAN length as a whole nor the MOD-97 check
 * digits — those are the responsibility of other collaborators.
 */
final class StructureCompiler
{
    private const CLASS_MAP = [
        'n' => '[0-9]',
        'a' => '[A-Z]',
        'c' => '[A-Z0-9]',
        'e' => '\ ',
    ];

    /** @var array<string, string> */
    private array $cache = [];

    public function toRegex(string $bbanStructure): string
    {
        if (isset($this->cache[$bbanStructure])) {
            return $this->cache[$bbanStructure];
        }

        preg_match_all('/(\d+)(!?)([nace])/', $bbanStructure, $matches, PREG_SET_ORDER);

        $pattern = '';

        /** @var array{0: string, 1: string, 2: string, 3: string} $token */
        foreach ($matches as $token) {
            $length     = $token[1];
            $isFixed    = $token[2] === '!';
            $class      = self::CLASS_MAP[$token[3]];
            $quantifier = $isFixed ? '{' . $length . '}' : '{1,' . $length . '}';

            $pattern .= '(' . $class . ')' . $quantifier;
        }

        $regex = '#^' . $pattern . '$#';

        $this->cache[$bbanStructure] = $regex;

        return $regex;
    }

    public function matches(string $bbanStructure, string $bban): bool
    {
        return preg_match($this->toRegex($bbanStructure), $bban) === 1;
    }
}
