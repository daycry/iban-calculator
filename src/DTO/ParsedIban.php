<?php

declare(strict_types=1);

namespace Daycry\Iban\DTO;

use Daycry\Iban\Enums\IbanFormat;

/**
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md §4
 */
final readonly class ParsedIban
{
    public function __construct(
        public string $countryCode,        // 'ES'
        public string $checkDigits,        // '91'
        public string $bban,               // BBAN normalizado
        public string $bankIdentifier,     // troceado por offsets del registry
        public ?string $branchIdentifier,  // null donde el país no tiene (DE/NL/BE)
        public string $accountNumber,
        public ?string $nationalCheckDigit, // solo extracción estructural en v1.0
        public bool $sepaCountry,          // del registry en código; útil con BD vacía
        public string $electronic,         // forma canónica normalizada
    ) {
    }

    public function format(IbanFormat $f = IbanFormat::Print): string
    {
        return (new \Daycry\Iban\Core\Formatter())->format($this->electronic, $f);
    }

    public function __toString(): string
    {
        return $this->electronic;
    }
}
