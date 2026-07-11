<?php

declare(strict_types=1);

namespace Tests\Providers;

use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Providers\ChainProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see ChainProvider} with spy providers: order is respected,
 * the first non-null result wins, and `supports()` is an OR across the
 * chain.
 *
 * @see \Daycry\Iban\Providers\ChainProvider
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class ChainProviderTest extends TestCase
{
    public function testSupportsIsTrueWhenAnyChainedProviderSupportsTheCountry(): void
    {
        $unsupporting = new class () implements ProviderInterface {
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
        };

        $supporting = new class () implements ProviderInterface {
            public function supports(string $countryCode): bool
            {
                return true;
            }

            public function findByIban(ParsedIban $iban): ?BankInfo
            {
                return null;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                return null;
            }
        };

        $chain = new ChainProvider([$unsupporting, $supporting]);

        self::assertTrue($chain->supports('ES'));
    }

    public function testSupportsIsFalseWhenNoChainedProviderSupportsTheCountry(): void
    {
        $neverSupports = new class () implements ProviderInterface {
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
        };

        $chain = new ChainProvider([$neverSupports, $neverSupports]);

        self::assertFalse($chain->supports('ES'));
    }

    public function testSupportsIsFalseForAnEmptyChain(): void
    {
        $chain = new ChainProvider([]);

        self::assertFalse($chain->supports('ES'));
    }

    public function testFindByIbanReturnsTheFirstNonNullResultInOrderAndSkipsTheSecondProvider(): void
    {
        $primaryInfo  = $this->fixedBankInfo('Primary Bank');
        $fallbackInfo = $this->fixedBankInfo('Fallback Bank');

        // Always resolves: findByIban()'s declared return type is
        // narrowed to non-nullable BankInfo (a valid covariant override of
        // ProviderInterface::findByIban()'s ?BankInfo), since this stub
        // body never actually returns null.
        $second = new class ($fallbackInfo) implements ProviderInterface {
            public int $findByIbanCalls = 0;

            public function __construct(private readonly BankInfo $bankInfo)
            {
            }

            public function supports(string $countryCode): bool
            {
                return true;
            }

            public function findByIban(ParsedIban $iban): BankInfo
            {
                $this->findByIbanCalls++;

                return $this->bankInfo;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                return null;
            }
        };

        $first = new class ($primaryInfo) implements ProviderInterface {
            public int $findByIbanCalls = 0;

            public function __construct(private readonly BankInfo $bankInfo)
            {
            }

            public function supports(string $countryCode): bool
            {
                return true;
            }

            public function findByIban(ParsedIban $iban): BankInfo
            {
                $this->findByIbanCalls++;

                return $this->bankInfo;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                return null;
            }
        };

        $chain = new ChainProvider([$first, $second]);

        $result = $chain->findByIban($this->parsedIban());

        self::assertSame($primaryInfo, $result);
        self::assertSame(1, $first->findByIbanCalls);
        self::assertSame(0, $second->findByIbanCalls, 'The second provider must not be consulted once the first already resolved.');
    }

    public function testFindByIbanFallsThroughToTheSecondProviderWhenTheFirstReturnsNull(): void
    {
        $fallbackInfo = $this->fixedBankInfo('Fallback Bank');

        $first = new class () implements ProviderInterface {
            public int $findByIbanCalls = 0;

            public function supports(string $countryCode): bool
            {
                return true;
            }

            public function findByIban(ParsedIban $iban): ?BankInfo
            {
                $this->findByIbanCalls++;

                return null;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                return null;
            }
        };

        $second = new class ($fallbackInfo) implements ProviderInterface {
            public int $findByIbanCalls = 0;

            public function __construct(private readonly BankInfo $bankInfo)
            {
            }

            public function supports(string $countryCode): bool
            {
                return true;
            }

            public function findByIban(ParsedIban $iban): BankInfo
            {
                $this->findByIbanCalls++;

                return $this->bankInfo;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                return null;
            }
        };

        $chain = new ChainProvider([$first, $second]);

        $result = $chain->findByIban($this->parsedIban());

        self::assertSame($fallbackInfo, $result);
        self::assertSame(1, $first->findByIbanCalls);
        self::assertSame(1, $second->findByIbanCalls);
    }

    public function testFindByIbanSkipsProvidersThatDoNotSupportTheCountry(): void
    {
        $fallbackInfo = $this->fixedBankInfo('Fallback Bank');

        $unsupporting = new class () implements ProviderInterface {
            public int $findByIbanCalls = 0;

            public function supports(string $countryCode): bool
            {
                return false;
            }

            public function findByIban(ParsedIban $iban): BankInfo
            {
                $this->findByIbanCalls++;

                return new BankInfo(
                    bankName: 'Should Never Be Returned',
                    shortName: null,
                    bic: null,
                    city: null,
                    address: null,
                    sepaSct: null,
                    sepaSctInst: null,
                    sepaSddCore: null,
                    sepaSddB2b: null,
                    sourceId: null,
                    sourceVersion: null,
                    sourceLicense: null,
                );
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                return null;
            }
        };

        $supporting = new class ($fallbackInfo) implements ProviderInterface {
            public function __construct(private readonly BankInfo $bankInfo)
            {
            }

            public function supports(string $countryCode): bool
            {
                return true;
            }

            public function findByIban(ParsedIban $iban): BankInfo
            {
                return $this->bankInfo;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                return null;
            }
        };

        $chain = new ChainProvider([$unsupporting, $supporting]);

        $result = $chain->findByIban($this->parsedIban());

        self::assertSame($fallbackInfo, $result);
        self::assertSame(0, $unsupporting->findByIbanCalls, 'A provider that does not support the country must never be queried.');
    }

    public function testFindByIbanReturnsNullWhenNoProviderResolves(): void
    {
        $alwaysNull = new class () implements ProviderInterface {
            public function supports(string $countryCode): bool
            {
                return true;
            }

            public function findByIban(ParsedIban $iban): ?BankInfo
            {
                return null;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                return null;
            }
        };

        $chain = new ChainProvider([$alwaysNull, $alwaysNull]);

        self::assertNull($chain->findByIban($this->parsedIban()));
    }

    public function testFindByBankCodeReturnsTheFirstNonNullResultInOrderAndSkipsTheSecondProvider(): void
    {
        $primaryInfo  = $this->fixedBankInfo('Primary Bank');
        $fallbackInfo = $this->fixedBankInfo('Fallback Bank');

        $second = new class ($fallbackInfo) implements ProviderInterface {
            public int $findByBankCodeCalls = 0;

            public function __construct(private readonly BankInfo $bankInfo)
            {
            }

            public function supports(string $countryCode): bool
            {
                return true;
            }

            public function findByIban(ParsedIban $iban): ?BankInfo
            {
                return null;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): BankInfo
            {
                $this->findByBankCodeCalls++;

                return $this->bankInfo;
            }
        };

        $first = new class ($primaryInfo) implements ProviderInterface {
            public int $findByBankCodeCalls = 0;

            public function __construct(private readonly BankInfo $bankInfo)
            {
            }

            public function supports(string $countryCode): bool
            {
                return true;
            }

            public function findByIban(ParsedIban $iban): ?BankInfo
            {
                return null;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): BankInfo
            {
                $this->findByBankCodeCalls++;

                return $this->bankInfo;
            }
        };

        $chain = new ChainProvider([$first, $second]);

        $result = $chain->findByBankCode('ES', '2100', '0418');

        self::assertSame($primaryInfo, $result);
        self::assertSame(1, $first->findByBankCodeCalls);
        self::assertSame(0, $second->findByBankCodeCalls);
    }

    private function fixedBankInfo(string $name): BankInfo
    {
        return new BankInfo(
            bankName: $name,
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );
    }

    private function parsedIban(): ParsedIban
    {
        return new ParsedIban(
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
    }
}
