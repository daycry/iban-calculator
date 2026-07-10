<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use Daycry\Iban\Core\Mod97;
use Daycry\Iban\Core\StructureCompiler;
use Daycry\Iban\Registry\CountryStructure;

/**
 * Deterministic per-country IBAN fixture generator, built purely from the
 * registry data + {@see Mod97}/{@see StructureCompiler} -- no static fixture
 * file is authored by hand, so this stays automatically in sync as countries
 * are added to (or edited in) the registry (T-16/17/18/28-30).
 *
 * All three generators are pure functions of an already-valid electronic
 * IBAN (or its {@see CountryStructure}); none of them require the framework
 * or a DB, matching the framework-free Core (see
 * tests/Architecture/CoreIsFrameworkFreeTest.php).
 *
 * @see .superpowers/sdd/task-45-50-brief.md
 */
final class IbanFixtures
{
    /**
     * Builds a SECOND valid electronic IBAN for the given country, distinct
     * from {@see CountryStructure::$ibanExampleElectronic}.
     *
     * Approach: scan the example's BBAN for the first digit character and
     * flip it to a different digit (0<->1). Every digit in an already-valid
     * BBAN necessarily sits in an `n` or `c` zone (the only token classes
     * whose charset includes `[0-9]`; an `a`/`e` zone could never contain a
     * digit and still match), so swapping digit-for-digit can never change
     * which token a position belongs to -- but {@see StructureCompiler} is
     * still consulted to double-check the mutated BBAN keeps matching
     * `bbanStructure`, per the T-45 brief's requirement. The two MOD-97
     * check digits are then recomputed for the mutated BBAN via
     * {@see Mod97::checkDigits()}, producing a second IBAN that is
     * independently valid (not just a copy of the example with different
     * check digits bolted on).
     *
     * Falls back to returning the example unchanged only if no digit
     * character exists anywhere in its BBAN (not observed for any of the 78
     * registered countries as of this writing, but kept as a defensive
     * fallback rather than throwing).
     */
    public static function secondValid(CountryStructure $country): string
    {
        $mod97    = new Mod97();
        $compiler = new StructureCompiler();
        $example  = $country->ibanExampleElectronic;
        $bban     = substr($example, 4);
        $length   = strlen($bban);

        for ($i = 0; $i < $length; $i++) {
            $char = $bban[$i];

            if ($char < '0' || $char > '9') {
                continue;
            }

            $mutatedDigit = $char === '0' ? '1' : '0';
            $mutatedBban  = substr_replace($bban, $mutatedDigit, $i, 1);

            if (!$compiler->matches($country->bbanStructure, $mutatedBban)) {
                continue;
            }

            $checkDigits = $mod97->checkDigits($country->countryCode, $mutatedBban);

            return $country->countryCode . $checkDigits . $mutatedBban;
        }

        return $example;
    }

    /**
     * Same-length, checksum-broken variant of a valid electronic IBAN: the
     * two check digits (offset 2, length 2) are replaced by a value that
     * provably fails {@see Mod97::isValid()} (searched exhaustively over
     * '00'..'99', skipping the original -- MOD-97's pigeonhole means at
     * most 2 of the 99 alternatives could coincidentally also validate, so
     * this always finds one).
     */
    public static function badChecksum(string $validElectronic): string
    {
        $mod97          = new Mod97();
        $cc             = substr($validElectronic, 0, 2);
        $originalCheck  = substr($validElectronic, 2, 2);
        $bban           = substr($validElectronic, 4);

        for ($candidate = 0; $candidate <= 99; $candidate++) {
            $check = str_pad((string) $candidate, 2, '0', STR_PAD_LEFT);

            if ($check === $originalCheck) {
                continue;
            }

            $attempt = $cc . $check . $bban;

            if (!$mod97->isValid($attempt)) {
                return $attempt;
            }
        }

        // Unreachable for real IBAN data (see docblock), but keeps the
        // return type total instead of nullable.
        return $cc . '00' . $bban;
    }

    /**
     * Length-broken variant of a valid electronic IBAN: drops the last
     * character, so the country code (and therefore the expected
     * `ibanLength`) still resolves, but the string is one char short.
     */
    public static function badLength(string $validElectronic): string
    {
        return substr($validElectronic, 0, -1);
    }
}
