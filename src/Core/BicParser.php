<?php

declare(strict_types=1);

namespace Daycry\Iban\Core;

use Daycry\Iban\DTO\ParsedBic;
use Daycry\Iban\Exceptions\InvalidBicException;

/**
 * Thin orchestration layer over {@see BicValidator}, mirroring {@see Parser}.
 *
 * `parse()`/`tryParse()` always validate first (via {@see BicValidator}) before
 * slicing the BIC into its structural fields. `parse()` throws on invalid
 * input; `tryParse()` returns null.
 *
 * A parsed BIC is well-formed with a recognised country code — a BIC has no
 * checksum, so this NEVER asserts the BIC exists on the SWIFT network.
 */
final class BicParser
{
    public function __construct(
        private BicValidator $validator,
    ) {
    }

    public function normalize(string $bic): string
    {
        return $this->validator->normalize($bic);
    }

    public function parse(string $bic): ParsedBic
    {
        $normalized = $this->validator->normalize($bic);
        $result     = $this->validator->validate($normalized);

        if (!$result->isValid()) {
            throw new InvalidBicException($result);
        }

        return $this->validator->toParsedBic($normalized);
    }

    public function tryParse(string $bic): ?ParsedBic
    {
        $normalized = $this->validator->normalize($bic);
        $result     = $this->validator->validate($normalized);

        return $result->isValid() ? $this->validator->toParsedBic($normalized) : null;
    }
}
