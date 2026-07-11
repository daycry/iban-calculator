<?php

declare(strict_types=1);

namespace Daycry\Iban\Contracts;

use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ParsedIban;

/**
 * Provider contract for bank data retrieval.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
interface ProviderInterface
{
    /**
     * Check if this provider supports the given country code.
     *
     * @param string $countryCode The ISO 3166-1 alpha-2 country code.
     *
     * @return bool True if this provider supports the country, false otherwise.
     */
    public function supports(string $countryCode): bool;

    /**
     * Find bank information by IBAN.
     *
     * @param ParsedIban $iban The parsed IBAN object.
     *
     * @return ?BankInfo The bank information, or null if not found.
     */
    public function findByIban(ParsedIban $iban): ?BankInfo;

    /**
     * Find bank information by country code and bank code.
     *
     * @param string      $countryCode The ISO 3166-1 alpha-2 country code.
     * @param string      $bankCode    The bank code.
     * @param string|null $branchCode  Optional branch code.
     *
     * @return ?BankInfo The bank information, or null if not found.
     */
    public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo;
}
