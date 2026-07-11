<?php

declare(strict_types=1);

namespace Daycry\Iban\Contracts;

use Daycry\Iban\DTO\BankResult;
use Daycry\Iban\DTO\ParsedIban;

/**
 * Resolver contract for resolving IBAN to bank information.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
interface ResolverInterface
{
    /**
     * Resolve an IBAN string or ParsedIban to bank information.
     *
     * @param string|ParsedIban $iban The IBAN to resolve (string or parsed object).
     *
     * @return BankResult The resolution result.
     */
    public function resolve(string|ParsedIban $iban): BankResult;
}
