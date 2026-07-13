<?php

declare(strict_types=1);

namespace Daycry\Iban\Contracts;

use Daycry\Iban\DTO\BankInfo;

/**
 * OPTIONAL, additive provider capability: resolving a bank directly from a
 * BIC (ISO 9362 / SWIFT code).
 *
 * **Why this is a separate interface, NOT a method on {@see ProviderInterface}.**
 * `ProviderInterface` is a published extension point — third-party packages and
 * consuming apps implement it to plug their own bank data source into the
 * {@see \Daycry\Iban\Resolver\Resolver}. Adding a new method to it would be a
 * backward-compatibility BREAK: every existing implementation out there would
 * suddenly become abstract/fatal ("Class X contains 1 abstract method"). This
 * interface is therefore additive: a provider MAY implement it to advertise the
 * extra capability, and consumers ({@see \Daycry\Iban\Resolver\Resolver},
 * {@see \Daycry\Iban\Providers\ChainProvider},
 * {@see \Daycry\Iban\Providers\CachedProvider}) detect support via
 * `instanceof BicProviderInterface` before calling it. A provider that does not
 * implement it (e.g. {@see \Daycry\Iban\Providers\NullProvider}) simply keeps
 * working — BIC resolution just returns `null` for it.
 *
 * Framework-free by design (same guarantee as {@see ProviderInterface}), so BIC
 * resolution stays usable standalone with no CodeIgniter and no database.
 */
interface BicProviderInterface
{
    /**
     * Resolve bank information from a BIC.
     *
     * Implementations SHOULD match on the BIC8 (the first 8 characters — the
     * institution's primary office), so an 8-character query resolves an
     * 11-character stored BIC and vice-versa, and normalize the input
     * (uppercase, whitespace-stripped) before matching.
     *
     * @param string $bic The BIC to resolve (8 or 11 chars; any case).
     *
     * @return ?BankInfo The bank information, or null if not found.
     */
    public function findByBic(string $bic): ?BankInfo;
}
