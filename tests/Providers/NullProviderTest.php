<?php

declare(strict_types=1);

namespace Tests\Providers;

use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Providers\NullProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class NullProviderTest extends TestCase
{
    private NullProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new NullProvider();
    }

    public function testSupportsAlwaysReturnsFalse(): void
    {
        self::assertFalse($this->provider->supports('ES'));
        self::assertFalse($this->provider->supports('DE'));
        self::assertFalse($this->provider->supports('XX'));
    }

    public function testFindByIbanAlwaysReturnsNull(): void
    {
        $parsed = new ParsedIban(
            countryCode: 'ES',
            checkDigits: '91',
            bban: '21000418450200051332',
            bankIdentifier: '2100',
            branchIdentifier: '0418',
            accountNumber: '450200051332',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'ES9121000418450200051332',
        );

        self::assertNull($this->provider->findByIban($parsed));
    }

    public function testFindByBankCodeAlwaysReturnsNull(): void
    {
        self::assertNull($this->provider->findByBankCode('ES', '2100', '0418'));
        self::assertNull($this->provider->findByBankCode('ES', '2100'));
    }
}
