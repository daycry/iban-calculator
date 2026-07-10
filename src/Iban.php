<?php

declare(strict_types=1);

namespace Daycry\Iban;

use Daycry\Iban\Contracts\ParserInterface;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\Contracts\ResolverInterface;
use Daycry\Iban\Contracts\ValidatorInterface;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\Core\Validator;
use Daycry\Iban\DTO\BankResult;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\DTO\ValidationResult;
use Daycry\Iban\Enums\IbanFormat;
use Daycry\Iban\Providers\NullProvider;
use Daycry\Iban\Registry\Registry;
use Daycry\Iban\Resolver\Resolver;

/**
 * Public API facade for IBAN validation, parsing, and resolution.
 *
 * Composes Validator → Parser → Resolver and implements all three
 * interface contracts via delegation. Default-constructible with
 * automatic sub-service wiring.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class Iban implements ValidatorInterface, ParserInterface, ResolverInterface
{
    private Validator $validator;
    private Parser $parser;
    private Resolver $resolver;

    public function __construct(
        Registry $registry = new Registry(),
        ProviderInterface $provider = new NullProvider(),
    ) {
        $this->validator = new Validator($registry);
        $this->parser    = new Parser($this->validator);
        $this->resolver  = new Resolver($this->parser, $provider);
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
}
