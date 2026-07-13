<?php

declare(strict_types=1);

namespace Tests\Providers;

use Daycry\Iban\Contracts\BicProviderInterface;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Providers\ChainProvider;
use Daycry\Iban\Providers\NullProvider;
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

    /**
     * `resolvedBy` is set by the individual provider (not by `ChainProvider`
     * itself); when the SECOND provider is the one that actually answers,
     * the returned `BankInfo`'s `resolvedBy` must be that provider's own
     * value, flowing through `ChainProvider` unchanged.
     */
    public function testFindByIbanPreservesTheAnsweringProvidersResolvedByWhenTheFirstReturnsNull(): void
    {
        $fallbackInfo = new BankInfo(
            bankName: 'Fallback Bank',
            shortName: null,
            bic: null,
            city: null,
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: 'iban.com',
            sourceVersion: null,
            sourceLicense: null,
            resolvedBy: 'iban.com',
        );

        $first = new class () implements ProviderInterface {
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

        $second = new class ($fallbackInfo) implements ProviderInterface {
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

        $chain = new ChainProvider([$first, $second]);

        $result = $chain->findByIban($this->parsedIban());

        self::assertSame($fallbackInfo, $result);
        self::assertInstanceOf(BankInfo::class, $result);
        self::assertSame('iban.com', $result->resolvedBy);
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

    // -- findByBic ---------------------------------------------------------

    public function testFindByBicSkipsProvidersThatDoNotImplementBicProviderInterface(): void
    {
        // A bare ProviderInterface (NO BicProviderInterface): its findByBic
        // does not even exist, so ChainProvider must skip it, not call it.
        $plain = new NullProvider();

        $bankInfo = $this->fixedBankInfo('BIC Bank');

        $bicCapable = new class ($bankInfo) implements BicProviderInterface, ProviderInterface {
            public int $findByBicCalls = 0;

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

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                return null;
            }

            public function findByBic(string $bic): BankInfo
            {
                $this->findByBicCalls++;

                return $this->bankInfo;
            }
        };

        $chain = new ChainProvider([$plain, $bicCapable]);

        $result = $chain->findByBic('CAIXESBBXXX');

        self::assertSame($bankInfo, $result);
        self::assertSame(1, $bicCapable->findByBicCalls);
    }

    public function testFindByBicReturnsTheFirstNonNullResultAndSkipsLaterProviders(): void
    {
        $primaryInfo  = $this->fixedBankInfo('Primary Bank');
        $fallbackInfo = $this->fixedBankInfo('Fallback Bank');

        $second = new class ($fallbackInfo) implements BicProviderInterface, ProviderInterface {
            public int $findByBicCalls = 0;

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

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                return null;
            }

            public function findByBic(string $bic): BankInfo
            {
                $this->findByBicCalls++;

                return $this->bankInfo;
            }
        };

        $first = new class ($primaryInfo) implements BicProviderInterface, ProviderInterface {
            public int $findByBicCalls = 0;

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

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                return null;
            }

            public function findByBic(string $bic): BankInfo
            {
                $this->findByBicCalls++;

                return $this->bankInfo;
            }
        };

        $chain = new ChainProvider([$first, $second]);

        $result = $chain->findByBic('CAIXESBBXXX');

        self::assertSame($primaryInfo, $result);
        self::assertSame(1, $first->findByBicCalls);
        self::assertSame(0, $second->findByBicCalls, 'The second provider must not be consulted once the first already resolved.');
    }

    public function testFindByBicReturnsNullWhenNoBicCapableProviderResolves(): void
    {
        $plain = new NullProvider();

        $bicNull = new class () implements BicProviderInterface, ProviderInterface {
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

            public function findByBic(string $bic): ?BankInfo
            {
                return null;
            }
        };

        $chain = new ChainProvider([$plain, $bicNull]);

        self::assertNull($chain->findByBic('CAIXESBBXXX'));
    }

    public function testFindByBicReturnsNullWhenNoProviderIsBicCapable(): void
    {
        $chain = new ChainProvider([new NullProvider(), new NullProvider()]);

        self::assertNull($chain->findByBic('CAIXESBBXXX'));
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
