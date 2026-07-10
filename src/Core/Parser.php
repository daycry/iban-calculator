<?php

declare(strict_types=1);

namespace Daycry\Iban\Core;

use Daycry\Iban\Contracts\ParserInterface;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Enums\IbanFormat;
use Daycry\Iban\Exceptions\InvalidIbanException;

/**
 * Thin orchestration layer on top of {@see Validator}, {@see Normalizer}
 * and {@see Formatter}.
 *
 * `parse()`/`tryParse()` always validate first (via {@see Validator}) before
 * slicing the IBAN into its structural fields; `format()` does NOT require
 * validity -- it merely normalizes and presents whatever electronic form it
 * is given.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class Parser implements ParserInterface
{
    public function __construct(
        private Validator $validator,
        private Normalizer $normalizer = new Normalizer(),
        private Formatter $formatter = new Formatter(),
    ) {
    }

    public function normalize(string $iban): string
    {
        return $this->normalizer->normalize($iban);
    }

    public function parse(string $iban): ParsedIban
    {
        $normalized = $this->normalizer->normalize($iban);
        $result     = $this->validator->validate($normalized);

        if (!$result->isValid()) {
            throw new InvalidIbanException($result);
        }

        return $this->validator->toParsedIban($normalized);
    }

    public function tryParse(string $iban): ?ParsedIban
    {
        $normalized = $this->normalizer->normalize($iban);
        $result     = $this->validator->validate($normalized);

        return $result->isValid() ? $this->validator->toParsedIban($normalized) : null;
    }

    public function format(string|ParsedIban $iban, IbanFormat $f = IbanFormat::Print): string
    {
        $electronic = $iban instanceof ParsedIban ? $iban->electronic : $this->normalizer->normalize($iban);

        return $this->formatter->format($electronic, $f);
    }
}
