<?php

declare(strict_types=1);

namespace Daycry\Iban\Providers;

use Daycry\Iban\Contracts\BicProviderInterface;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ParsedIban;

/**
 * {@see ProviderInterface} composite: tries an ordered list of providers and
 * returns the first non-null result, in order.
 *
 * Purely a composition seam over other {@see ProviderInterface} instances —
 * it holds no CI4 dependency itself (framework-free, unlike
 * {@see IbanComProvider} or {@see DatabaseProvider}, which do live in this
 * same, unguarded `Providers/` directory).
 *
 * The intended use ({@see \Daycry\Iban\Config\Services::iban()}) is a primary
 * provider (e.g. the local `banks` table via {@see DatabaseProvider}) tried
 * first, with a remote fallback (e.g. {@see IbanComProvider}) tried only when
 * the primary provider returns nothing for the IBAN's country.
 *
 * Also implements {@see BicProviderInterface}: {@see findByBic()} walks the
 * same ordered chain but SKIPS any provider that does not itself implement
 * `BicProviderInterface` (e.g. a bare {@see \Daycry\Iban\Providers\NullProvider}),
 * so mixing BIC-capable and BIC-incapable providers in one chain is safe.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class ChainProvider implements BicProviderInterface, ProviderInterface
{
    /**
     * @param list<ProviderInterface> $providers Tried in order; the first
     *                                            non-null result wins.
     */
    public function __construct(private readonly array $providers)
    {
    }

    /**
     * True when ANY chained provider supports the country.
     */
    public function supports(string $countryCode): bool
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($countryCode)) {
                return true;
            }
        }

        return false;
    }

    public function findByIban(ParsedIban $iban): ?BankInfo
    {
        foreach ($this->providers as $provider) {
            if (! $provider->supports($iban->countryCode)) {
                continue;
            }

            $info = $provider->findByIban($iban);

            if ($info !== null) {
                return $info;
            }
        }

        return null;
    }

    public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
    {
        foreach ($this->providers as $provider) {
            if (! $provider->supports($countryCode)) {
                continue;
            }

            $info = $provider->findByBankCode($countryCode, $bankCode, $branchCode);

            if ($info !== null) {
                return $info;
            }
        }

        return null;
    }

    /**
     * Walks the chain in order, consulting only providers that implement
     * {@see BicProviderInterface}; the first non-null result wins. Providers
     * lacking the capability are silently skipped (not an error), so a chain
     * may freely mix BIC-capable and BIC-incapable providers.
     */
    public function findByBic(string $bic): ?BankInfo
    {
        foreach ($this->providers as $provider) {
            if (! $provider instanceof BicProviderInterface) {
                continue;
            }

            $info = $provider->findByBic($bic);

            if ($info !== null) {
                return $info;
            }
        }

        return null;
    }
}
