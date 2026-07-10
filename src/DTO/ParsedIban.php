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

    // TODO(T-25): extraer a Daycry\Iban\Core\Formatter y delegar
    public function format(IbanFormat $f = IbanFormat::Print): string
    {
        return match ($f) {
            IbanFormat::Electronic  => $this->electronic,
            IbanFormat::Print       => trim(chunk_split($this->electronic, 4, ' ')),
            IbanFormat::Anonymized  => $this->anonymize(),
        };
    }

    private function anonymize(): string
    {
        $len = strlen($this->electronic);
        if ($len <= 6) {
            return $this->electronic;
        }
        // Visible: código de país (2) + últimos 4; resto enmascarado con '*'
        return substr($this->electronic, 0, 2)
            . str_repeat('*', $len - 6)
            . substr($this->electronic, -4);
    }

    public function __toString(): string
    {
        return $this->electronic;
    }
}
