<?php

declare(strict_types=1);

namespace Daycry\Iban\Resolver;

use Daycry\Iban\Contracts\BicProviderInterface;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\Contracts\ResolverInterface;
use Daycry\Iban\Core\BicValidator;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\BankResult;
use Daycry\Iban\DTO\ParsedBic;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Providers\NullProvider;

/**
 * Composes a {@see BankResult} from a {@see ParsedIban} plus an optional
 * provider overlay.
 *
 * Precedence when the provider supports the IBAN's country: `findByIban()`
 * is tried first (an exact match on the IBAN's own bank + branch); if it
 * returns null, `findByBankCode()` is used as a BANK-LEVEL fallback — the
 * branch code is deliberately omitted (passed as `null`) so that an IBAN
 * whose specific branch isn't stored still resolves to its bank when only a
 * bank-level row (`branch_code IS NULL`) was seeded. This matters for every
 * country whose IBAN carries a branch segment (e.g. ES/GR/HU/MT/PL/…) but
 * whose bundled importer publishes bank-level rows only.
 *
 * When given a string, it is parsed via {@see Parser::parse()}
 * (which throws {@see \Daycry\Iban\Exceptions\InvalidIbanException} on
 * invalid input); when given an already-parsed {@see ParsedIban}, it is
 * used as-is without re-parsing.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class Resolver implements ResolverInterface
{
    public function __construct(
        private Parser $parser,
        private ProviderInterface $provider = new NullProvider(),
        private BicValidator $bicValidator = new BicValidator(),
    ) {
    }

    public function resolve(string|ParsedIban $iban): BankResult
    {
        $parsed = $iban instanceof ParsedIban ? $iban : $this->parser->parse($iban);

        $info = null;
        if ($this->provider->supports($parsed->countryCode)) {
            $info = $this->provider->findByIban($parsed)
                ?? $this->provider->findByBankCode(
                    $parsed->countryCode,
                    $parsed->bankIdentifier,
                    null,
                );
        }

        return new BankResult(
            iban: $parsed,
            bankName: $info?->bankName,
            shortName: $info?->shortName,
            bic: $info?->bic,
            city: $info?->city,
            address: $info?->address,
            sepaSct: $info?->sepaSct,
            sepaSctInst: $info?->sepaSctInst,
            sepaSddCore: $info?->sepaSddCore,
            sepaSddB2b: $info?->sepaSddB2b,
            sourceId: $info?->sourceId,
            sourceVersion: $info?->sourceVersion,
            sourceLicense: $info?->sourceLicense,
            resolvedBy: $info?->resolvedBy,
        );
    }

    /**
     * Resolves a bank straight from a BIC, returning `null` (never throwing)
     * when the BIC is not well-formed OR the configured provider cannot answer.
     *
     * The BIC is validated/normalized FIRST — a malformed BIC short-circuits to
     * `null` WITHOUT ever touching the provider. When the BIC is well-formed,
     * the provider is consulted only if it implements {@see BicProviderInterface}
     * (e.g. {@see \Daycry\Iban\Providers\DatabaseProvider}); a provider without
     * that capability (e.g. the default {@see NullProvider}) yields `null`, so
     * BIC resolution degrades gracefully to "no provider" just like IBAN
     * resolution does. This method is intentionally NOT part of
     * {@see ResolverInterface} (adding it would break existing implementers),
     * and it depends only on framework-free collaborators so the resolver stays
     * usable without CodeIgniter.
     */
    public function resolveBic(string|ParsedBic $bic): ?BankInfo
    {
        $normalized = $bic instanceof ParsedBic ? $bic->bic : $this->bicValidator->normalize($bic);

        if (! $this->bicValidator->isValid($normalized)) {
            return null;
        }

        if (! $this->provider instanceof BicProviderInterface) {
            return null;
        }

        return $this->provider->findByBic($normalized);
    }
}
