<?php

declare(strict_types=1);

namespace Daycry\Iban;

use Daycry\Iban\Contracts\ParserInterface;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\Contracts\ResolverInterface;
use Daycry\Iban\Contracts\ValidatorInterface;
use Daycry\Iban\Core\BicParser;
use Daycry\Iban\Core\BicValidator;
use Daycry\Iban\Core\IbanBicCrossChecker;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\BankResult;
use Daycry\Iban\DTO\ParsedBic;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\DTO\ValidationResult;
use Daycry\Iban\DTO\Violation;
use Daycry\Iban\Enums\IbanFormat;
use Daycry\Iban\Enums\ViolationCode;
use Daycry\Iban\Providers\NullProvider;
use Daycry\Iban\Registry\IsoCountryRegistry;
use Daycry\Iban\Registry\Registry;
use Daycry\Iban\Resolver\Resolver;

/**
 * Public API facade for IBAN validation, parsing, and resolution — plus
 * ISO 9362 BIC validation/parsing and IBAN↔BIC cross-checking.
 *
 * Composes Validator → Parser → Resolver (IBAN) and BicValidator → BicParser
 * (BIC), and implements the three IBAN interface contracts via delegation.
 * Default-constructible with automatic sub-service wiring.
 *
 * BIC note: a BIC has no checksum, so `validateBic()`/`isValidBic()` only ever
 * mean "well-formed + recognised country", never "this BIC exists".
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class Iban implements ValidatorInterface, ParserInterface, ResolverInterface
{
    private Validator $validator;
    private Parser $parser;
    private Resolver $resolver;
    private BicValidator $bicValidator;
    private BicParser $bicParser;
    private IbanBicCrossChecker $crossChecker;

    public function __construct(
        Registry $registry = new Registry(),
        ProviderInterface $provider = new NullProvider(),
        IsoCountryRegistry $isoCountries = new IsoCountryRegistry(),
    ) {
        $this->validator    = new Validator($registry);
        $this->parser       = new Parser($this->validator);
        $this->resolver     = new Resolver($this->parser, $provider);
        $this->bicValidator = new BicValidator($isoCountries);
        $this->bicParser    = new BicParser($this->bicValidator);
        $this->crossChecker = new IbanBicCrossChecker($registry);
    }

    // ValidatorInterface implementation
    public function validate(string|ParsedIban $iban, bool $checkNational = false): ValidationResult
    {
        return $this->validator->validate($iban, $checkNational);
    }

    public function isValid(string|ParsedIban $iban): bool
    {
        return $this->validator->isValid($iban);
    }

    // ParserInterface implementation
    public function normalize(string $iban): string
    {
        return $this->parser->normalize($iban);
    }

    public function parse(string $iban): ParsedIban
    {
        return $this->parser->parse($iban);
    }

    public function tryParse(string $iban): ?ParsedIban
    {
        return $this->parser->tryParse($iban);
    }

    public function format(string|ParsedIban $iban, IbanFormat $f = IbanFormat::Print): string
    {
        return $this->parser->format($iban, $f);
    }

    // ResolverInterface implementation
    public function resolve(string|ParsedIban $iban): BankResult
    {
        return $this->resolver->resolve($iban);
    }

    // BIC (ISO 9362) API

    /**
     * Validate a BIC for well-formedness. NEVER throws.
     *
     * A BIC has no checksum, so a valid result means "well-formed AND its
     * country code is recognised", never "this BIC exists on the SWIFT
     * network".
     */
    public function validateBic(string|ParsedBic $bic): ValidationResult
    {
        return $this->bicValidator->validate($bic);
    }

    public function isValidBic(string|ParsedBic $bic): bool
    {
        return $this->bicValidator->isValid($bic);
    }

    public function normalizeBic(string $bic): string
    {
        return $this->bicParser->normalize($bic);
    }

    public function parseBic(string $bic): ParsedBic
    {
        return $this->bicParser->parse($bic);
    }

    public function tryParseBic(string $bic): ?ParsedBic
    {
        return $this->bicParser->tryParse($bic);
    }

    /**
     * The "one, the other, or both" entry point: validate an IBAN, a BIC, or
     * both, and — when both are supplied AND each is individually valid — also
     * cross-check them for mutual coherence (country and, where structurally
     * possible, bank code).
     *
     * - Neither supplied (both null/blank) → a single {@see ViolationCode::NothingToValidate}.
     * - Only one supplied → just that value's violations.
     * - Both supplied and both valid → any cross-check violations (empty if coherent).
     * - Both supplied but one is structurally invalid → that value's violations
     *   ONLY; the cross-check is skipped (garbage in must not manufacture a
     *   bogus mismatch).
     */
    public function validateIbanAndBic(?string $iban, ?string $bic): ValidationResult
    {
        $iban = ($iban !== null && trim($iban) !== '') ? $iban : null;
        $bic  = ($bic !== null && trim($bic) !== '') ? $bic : null;

        if ($iban === null && $bic === null) {
            return new ValidationResult(false, [new Violation(
                ViolationCode::NothingToValidate,
                'iban_bic.violation.nothing_to_validate',
                'Neither an IBAN nor a BIC was provided.'
            )]);
        }

        $violations = [];
        $ibanValid  = false;
        $bicValid   = false;

        if ($iban !== null) {
            $result    = $this->validator->validate($iban);
            $ibanValid = $result->isValid();
            if (!$ibanValid) {
                $violations = array_merge($violations, $result->violations());
            }
        }

        if ($bic !== null) {
            $result   = $this->bicValidator->validate($bic);
            $bicValid = $result->isValid();
            if (!$bicValid) {
                $violations = array_merge($violations, $result->violations());
            }
        }

        if ($iban !== null && $bic !== null && $ibanValid && $bicValid) {
            $violations = array_merge(
                $violations,
                $this->crossChecker->check($this->parser->parse($iban), $this->bicParser->parse($bic))
            );
        }

        return new ValidationResult($violations === [], $violations);
    }

    // Sub-service accessors
    public function validator(): Validator
    {
        return $this->validator;
    }

    public function parser(): Parser
    {
        return $this->parser;
    }

    public function resolver(): Resolver
    {
        return $this->resolver;
    }

    public function bicValidator(): BicValidator
    {
        return $this->bicValidator;
    }

    public function bicParser(): BicParser
    {
        return $this->bicParser;
    }
}
