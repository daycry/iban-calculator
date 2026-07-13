<?php

declare(strict_types=1);

namespace Daycry\Iban\Core;

use Daycry\Iban\DTO\ParsedBic;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\DTO\Violation;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Registry\Registry;

/**
 * Cross-checks a {@see ParsedIban} against a {@see ParsedBic} for mutual
 * coherence. Framework-free and database-free: it reasons purely from the two
 * parsed values and the in-code IBAN structural {@see Registry}.
 *
 * Intended to run ONLY when both an IBAN and a BIC were supplied AND each is
 * individually valid — feeding it a structurally invalid value would only
 * manufacture a bogus mismatch. It returns a (possibly empty) list of
 * {@see Violation}s; an empty list means "coherent".
 *
 * Two independent checks:
 *
 *   - Country: the BIC's country code (positions 5-6) must equal the IBAN's
 *     country code. A mismatch is always a real incoherence.
 *
 *   - Bank: emitted ONLY for countries whose IBAN bank-code segment is exactly
 *     four ALPHABETIC characters. This is country-conditional by necessity, not
 *     convenience: only for a 4-letter-alpha bank code is the IBAN's bank
 *     segment guaranteed to be the very same token as the BIC's institution
 *     code (BIC positions 1-4) — e.g. GB `NWBK`… ↔ `NWBK`GB2L. For a NUMERIC
 *     bank code (ES, DE, FR, …) there is no structural relationship between the
 *     IBAN's digits and the BIC's letters, so ANY comparison would be a guess;
 *     we deliberately emit nothing rather than a false positive.
 *
 *     The 4-alpha country set is DERIVED at runtime from each country's
 *     {@see \Daycry\Iban\Registry\CountryStructure} (bank-segment length 4 whose
 *     BBAN token class is pure `a`), never hardcoded — so it tracks the registry.
 */
final class IbanBicCrossChecker
{
    public function __construct(
        private Registry $registry = new Registry(),
    ) {
    }

    /**
     * @return Violation[] Empty when the IBAN and BIC are coherent.
     */
    public function check(ParsedIban $iban, ParsedBic $bic): array
    {
        $violations = [];

        if ($bic->countryCode !== $iban->countryCode) {
            $violations[] = new Violation(
                ViolationCode::BicIbanCountryMismatch,
                'bic.violation.iban_country_mismatch',
                'The BIC country code does not match the IBAN country code.'
            );
        }

        if ($this->bankCodeIsFourAlpha($iban->countryCode) && $iban->bankIdentifier !== $bic->institutionCode) {
            $violations[] = new Violation(
                ViolationCode::BicIbanBankMismatch,
                'bic.violation.iban_bank_mismatch',
                'The BIC institution code does not match the IBAN bank code.'
            );
        }

        return $violations;
    }

    /**
     * Whether the given country's IBAN bank-code segment is exactly four
     * alphabetic characters (so it equals a BIC's institution code).
     *
     * Derived from the registry's {@see \Daycry\Iban\Registry\CountryStructure}:
     * the bank segment must be length 4 AND overlap only BBAN tokens of char
     * class `a`. Uses the same `bbanStructure` token grammar as
     * {@see StructureCompiler}. Returns false for unknown countries.
     */
    private function bankCodeIsFourAlpha(string $countryCode): bool
    {
        if (!$this->registry->has($countryCode)) {
            return false;
        }

        $structure              = $this->registry->get($countryCode);
        [$bankOffset, $bankLen] = $structure->bank;

        if ($bankLen !== 4) {
            return false;
        }

        preg_match_all('/(\d+)(!?)([nace])/', $structure->bbanStructure, $matches, PREG_SET_ORDER);

        // The BBAN begins at offset 4 within the normalized IBAN (country code
        // + check digits occupy 0-3), so translate the bank segment's offset
        // into a BBAN-relative window before walking the tokens.
        $bankStart = $bankOffset - 4;
        $bankEnd   = $bankStart + $bankLen;

        $position = 0;
        $classes  = [];

        /** @var array{0: string, 1: string, 2: string, 3: string} $token */
        foreach ($matches as $token) {
            $length = (int) $token[1];
            $class  = $token[3];

            if (max($position, $bankStart) < min($position + $length, $bankEnd)) {
                $classes[$class] = true;
            }

            $position += $length;
        }

        return $classes === ['a' => true];
    }
}
