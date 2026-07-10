<?php

declare(strict_types=1);

namespace Daycry\Iban\Core;

use Daycry\Iban\Enums\IbanFormat;

/**
 * Pure formatter for Electronic/Print/Anonymized IBAN formats.
 *
 * Formatter is stateless and framework-free. It assumes input is already normalized
 * (uppercase, without spaces). It does not import DTO classes to maintain independence.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §4
 */
final class Formatter
{
    /**
     * Format an IBAN string according to the specified format.
     *
     * @param string $electronic The electronic (canonical) IBAN format without spaces
     * @param IbanFormat $f The desired output format
     * @return string The formatted IBAN
     */
    public function format(string $electronic, IbanFormat $f = IbanFormat::Print): string
    {
        return match ($f) {
            IbanFormat::Electronic => $electronic,
            IbanFormat::Print      => trim(chunk_split($electronic, 4, ' ')),
            IbanFormat::Anonymized => $this->anonymize($electronic),
        };
    }

    /**
     * Anonymize an IBAN by masking the middle digits.
     *
     * Shows only country code (first 2) and last 4 digits; masks the rest with '*'.
     * Short IBANs (length <= 6) are returned unchanged.
     *
     * @param string $electronic The electronic format IBAN
     * @return string The anonymized IBAN
     */
    private function anonymize(string $electronic): string
    {
        $len = strlen($electronic);
        if ($len <= 6) {
            return $electronic;
        }
        return substr($electronic, 0, 2) . str_repeat('*', $len - 6) . substr($electronic, -4);
    }
}
