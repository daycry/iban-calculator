<?php

declare(strict_types=1);

namespace Daycry\Iban\Contracts;

/**
 * RegistryLoader contract for loading raw country bank data.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
interface RegistryLoaderInterface
{
    /**
     * Load raw bank registry data by country.
     *
     * @return array<string, mixed> Raw registry data keyed by country code.
     */
    public function load(): array;
}
