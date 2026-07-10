<?php

declare(strict_types=1);

namespace Daycry\Iban\Providers;

use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ParsedIban;

/**
 * Default null-object provider: supports nothing and resolves nothing.
 *
 * Used as the {@see \Daycry\Iban\Resolver\Resolver} default so bank-data
 * resolution degrades gracefully to "no provider configured" instead of
 * requiring a database or external source.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class NullProvider implements ProviderInterface
{
    public function supports(string $countryCode): bool
    {
        return false;
    }

    public function findByIban(ParsedIban $iban): ?BankInfo
    {
        return null;
    }

    public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
    {
        return null;
    }
}
