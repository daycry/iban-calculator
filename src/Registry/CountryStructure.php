<?php

declare(strict_types=1);

namespace Daycry\Iban\Registry;

/**
 * DTO that models the structure of a country in the registry.
 *
 * Holds the IBAN format specification for a specific country, including
 * the country code, IBAN length, BBAN structure tokens, and positional
 * metadata for bank, branch, account, and national check digit fields.
 */
final readonly class CountryStructure
{
    /**
     * @param string $countryCode Two-letter ISO country code (e.g., 'ES', 'GB').
     * @param int $ibanLength Fixed IBAN length for this country.
     * @param string $bbanStructure SWIFT format tokens (e.g., '4!n4!n2!n10!n' for Spain).
     * @param array{0:int,1:int} $bank Offset and length of the bank field within the normalized IBAN.
     * @param array{0:int,1:int}|null $branch Offset and length of the branch field, or null if not used.
     * @param array{0:int,1:int} $account Offset and length of the account number field.
     * @param array{0:int,1:int}|null $nationalCheck Offset and length of the national check digit, or null if not used.
     * @param bool $sepa Whether this country is part of the SEPA region (EPC409-09).
     * @param string $ibanExampleElectronic Example IBAN in electronic format.
     */
    public function __construct(
        public string $countryCode,
        public int $ibanLength,
        public string $bbanStructure,
        public array $bank,
        public ?array $branch,
        public array $account,
        public ?array $nationalCheck,
        public bool $sepa,
        public string $ibanExampleElectronic,
    ) {
    }
}
