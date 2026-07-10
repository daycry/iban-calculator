<?php

declare(strict_types=1);

namespace Daycry\Iban\Core;

use Daycry\Iban\Contracts\NationalCheckValidatorInterface;
use Daycry\Iban\Contracts\ValidatorInterface;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\DTO\ValidationResult;
use Daycry\Iban\DTO\Violation;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Registry\Registry;

/**
 * Check-ordering pipeline: the heart of IBAN validation.
 *
 * Normalizes the input (unless it is already a {@see ParsedIban}) and then
 * runs a fixed sequence of checks, ordered from cheapest/most-specific to
 * most-expensive, short-circuiting and returning on the FIRST violation
 * found:
 *
 *   1. Blank
 *   2. TooShort
 *   3. IllegalCharacters
 *   4. UnknownCountry
 *   5. BadLength
 *   6. MalformedStructure
 *   7. ChecksumFailed
 *   8. NationalCheckFailed (only when `$checkNational` is true AND a
 *      national validator is registered for the country; otherwise this
 *      step is a silent skip, never a failure)
 *
 * `validate()`/`isValid()` never throw, even on garbage input; violations
 * are always reported via the returned {@see ValidationResult}.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class Validator implements ValidatorInterface
{
    public function __construct(
        private Registry $registry,
        private Normalizer $normalizer = new Normalizer(),
        private StructureCompiler $compiler = new StructureCompiler(),
        private Mod97 $mod97 = new Mod97(),
        /** @var array<string, NationalCheckValidatorInterface> keyed by upper-case country code */
        private array $nationalValidators = [],
    ) {
    }

    public function validate(string|ParsedIban $iban, bool $checkNational = false): ValidationResult
    {
        $normalized = $iban instanceof ParsedIban ? $iban->electronic : $this->normalizer->normalize($iban);

        if ($normalized === '') {
            return $this->violation(
                ViolationCode::Blank,
                'iban.violation.blank',
                'The IBAN is empty.'
            );
        }

        if (strlen($normalized) < 4) {
            return $this->violation(
                ViolationCode::TooShort,
                'iban.violation.too_short',
                'The IBAN is too short.'
            );
        }

        if (preg_match('/[^A-Z0-9]/', $normalized) === 1) {
            return $this->violation(
                ViolationCode::IllegalCharacters,
                'iban.violation.illegal_characters',
                'The IBAN contains illegal characters.'
            );
        }

        $cc = substr($normalized, 0, 2);

        if (!$this->registry->has($cc)) {
            return $this->violation(
                ViolationCode::UnknownCountry,
                'iban.violation.unknown_country',
                'Unknown or unsupported IBAN country code.'
            );
        }

        $structure = $this->registry->get($cc);

        if (strlen($normalized) !== $structure->ibanLength) {
            return $this->violation(
                ViolationCode::BadLength,
                'iban.violation.bad_length',
                'The IBAN length is invalid for its country.'
            );
        }

        $checkDigits = substr($normalized, 2, 2);
        $bban        = substr($normalized, 4);

        if (!ctype_digit($checkDigits) || !$this->compiler->matches($structure->bbanStructure, $bban)) {
            return $this->violation(
                ViolationCode::MalformedStructure,
                'iban.violation.malformed_structure',
                'The IBAN structure is malformed.'
            );
        }

        if (!$this->mod97->isValid($normalized)) {
            return $this->violation(
                ViolationCode::ChecksumFailed,
                'iban.violation.checksum_failed',
                'The IBAN check digits are invalid.'
            );
        }

        if ($checkNational && isset($this->nationalValidators[$cc])) {
            $national = $this->nationalValidators[$cc];

            if (!$national->verify($this->toParsedIban($normalized))) {
                return $this->violation(
                    ViolationCode::NationalCheckFailed,
                    'iban.violation.national_check_failed',
                    'The national check digits are invalid.'
                );
            }
        }

        return new ValidationResult(true, []);
    }

    public function isValid(string|ParsedIban $iban): bool
    {
        return $this->validate($iban)->isValid();
    }

    /**
     * Slice an already-valid, normalized IBAN of a known country into its
     * structural fields, per the registered offsets for its country.
     *
     * Assumes `$normalized` is already a valid IBAN for a known country
     * (i.e. it has passed at least the UnknownCountry and BadLength steps
     * of {@see validate()}). Reused by the Parser (T-24) and the national
     * check hook (step 8 above).
     */
    public function toParsedIban(string $normalized): ParsedIban
    {
        $cc    = substr($normalized, 0, 2);
        $s     = $this->registry->get($cc);
        $slice = static fn (?array $o): ?string => $o === null ? null : substr($normalized, $o[0], $o[1]);

        return new ParsedIban(
            countryCode: $cc,
            checkDigits: substr($normalized, 2, 2),
            bban: substr($normalized, 4),
            bankIdentifier: substr($normalized, $s->bank[0], $s->bank[1]),
            branchIdentifier: $slice($s->branch),
            accountNumber: substr($normalized, $s->account[0], $s->account[1]),
            nationalCheckDigit: $slice($s->nationalCheck),
            sepaCountry: $s->sepa,
            electronic: $normalized,
        );
    }

    private function violation(ViolationCode $code, string $messageKey, string $message): ValidationResult
    {
        return new ValidationResult(false, [new Violation($code, $messageKey, $message)]);
    }
}
