<?php

declare(strict_types=1);

namespace Daycry\Iban\Resolver;

use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\Contracts\ResolverInterface;
use Daycry\Iban\Core\Parser;
use Daycry\Iban\DTO\BankResult;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Providers\NullProvider;

/**
 * Composes a {@see BankResult} from a {@see ParsedIban} plus an optional
 * provider overlay.
 *
 * Precedence when the provider supports the IBAN's country: `findByIban()`
 * is tried first; if it returns null, `findByBankCode()` is used as a
 * fallback. When given a string, it is parsed via {@see Parser::parse()}
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
                    $parsed->branchIdentifier,
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
        );
    }
}
