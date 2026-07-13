<?php

declare(strict_types=1);

namespace Tests\Providers;

use Closure;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Iban\Config\Iban as IbanConfig;
use Daycry\Iban\Config\Services as IbanServices;
use Daycry\Iban\Contracts\BicProviderInterface;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ParsedIban;
use Daycry\Iban\Iban as IbanService;
use Daycry\Iban\Providers\CachedProvider;
use Daycry\Iban\Providers\DatabaseProvider;
use Daycry\Iban\Providers\NullProvider;
use Daycry\Iban\Resolver\Resolver;
use ReflectionProperty;

/**
 * Exercises the `CachedProvider` decorator (V-5): a spy inner
 * `ProviderInterface` counts `findByBankCode()` calls, wrapped behind an
 * in-memory `CacheInterface` test double so assertions are deterministic
 * (no filesystem-backed cache handler / real TTL expiry involved).
 *
 * @see \Daycry\Iban\Providers\CachedProvider
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class CachedProviderTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        // The Services-wiring tests below mutate the shared `Config\Iban`
        // singleton; undo that so later tests keep seeing the documented
        // defaults, and drop any shared `service('iban')` built against it.
        config(IbanConfig::class)->provider = 'null';
        config(IbanConfig::class)->cacheTtl = null;
        $this->resetServices();
    }

    public function testSupportsDelegatesToTheInnerProvider(): void
    {
        $inner = new class () implements ProviderInterface {
            public function supports(string $countryCode): bool
            {
                return $countryCode === 'ES';
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

        $cached = new CachedProvider($inner, $this->makeInMemoryCache());

        self::assertTrue($cached->supports('ES'));
        self::assertFalse($cached->supports('DE'));
    }

    public function testSecondIdenticalFindByBankCodeIsServedFromCacheNotTheInnerProvider(): void
    {
        $bankInfo = $this->fixedBankInfo();

        $spy = new class ($bankInfo) implements ProviderInterface {
            public int $calls = 0;

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

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): BankInfo
            {
                $this->calls++;

                return $this->bankInfo;
            }
        };

        $cached = new CachedProvider($spy, $this->makeInMemoryCache());

        $first = $cached->findByBankCode('ES', '2100', '0418');
        self::assertSame($bankInfo, $first);
        self::assertSame(1, $spy->calls);

        $second = $cached->findByBankCode('ES', '2100', '0418');
        self::assertSame($bankInfo, $second);
        self::assertSame(1, $spy->calls, 'The second identical lookup must be served from cache, not re-query the inner provider.');
    }

    /**
     * `resolvedBy` is metadata carried on the cached `BankInfo` itself; a
     * cached hit (served from `CacheInterface::get()`, never re-querying the
     * inner provider) must preserve it exactly like every other field.
     */
    public function testCachedHitPreservesResolvedBy(): void
    {
        $bankInfo = new BankInfo(
            bankName: 'Commerzbank',
            shortName: null,
            bic: 'COBADEFFXXX',
            city: 'Berlin',
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

        $spy = new class ($bankInfo) implements ProviderInterface {
            public int $calls = 0;

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
                $this->calls++;

                return $this->bankInfo;
            }
        };

        $cached = new CachedProvider($spy, $this->makeInMemoryCache());

        $first = $cached->findByBankCode('DE', '37040044');
        self::assertInstanceOf(BankInfo::class, $first);
        self::assertSame('iban.com', $first->resolvedBy);
        self::assertSame(1, $spy->calls);

        // Served from the cache, not the inner provider -- resolvedBy must survive.
        $second = $cached->findByBankCode('DE', '37040044');
        self::assertInstanceOf(BankInfo::class, $second);
        self::assertSame('iban.com', $second->resolvedBy);
        self::assertSame(1, $spy->calls, 'The second identical lookup must be served from cache.');
    }

    public function testAMissIsCachedSoTheInnerProviderIsNotReQueried(): void
    {
        $spy = new class () implements ProviderInterface {
            public int $calls = 0;

            public function supports(string $countryCode): bool
            {
                return true;
            }

            public function findByIban(ParsedIban $iban): ?BankInfo
            {
                $this->calls++;

                return null;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                $this->calls++;

                return null;
            }
        };

        $cached = new CachedProvider($spy, $this->makeInMemoryCache());

        $first = $cached->findByBankCode('ES', '9999', '0000');
        self::assertNull($first);
        self::assertSame(1, $spy->calls);

        $second = $cached->findByBankCode('ES', '9999', '0000');
        self::assertNull($second, 'A cached miss must still resolve to null, not the sentinel.');
        self::assertSame(1, $spy->calls, 'A cached miss must not re-query the inner provider.');
    }

    public function testFindByIbanSharesTheSameCacheEntryAsFindByBankCode(): void
    {
        $bankInfo = $this->fixedBankInfo();

        $spy = new class ($bankInfo) implements ProviderInterface {
            public int $calls = 0;

            public function __construct(private readonly BankInfo $bankInfo)
            {
            }

            public function supports(string $countryCode): bool
            {
                return true;
            }

            public function findByIban(ParsedIban $iban): ?BankInfo
            {
                \PHPUnit\Framework\Assert::fail('CachedProvider::findByIban() must delegate to its own findByBankCode(), never the inner findByIban().');
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): BankInfo
            {
                $this->calls++;

                return $this->bankInfo;
            }
        };

        $cached = new CachedProvider($spy, $this->makeInMemoryCache());

        $parsed = $this->parsedIban('ES', '2100', '0418');

        $viaIban = $cached->findByIban($parsed);
        self::assertSame($bankInfo, $viaIban);
        self::assertSame(1, $spy->calls);

        // Same natural key via the other entry point: must be served from
        // the cache entry findByIban() already populated.
        $viaBankCode = $cached->findByBankCode('ES', '2100', '0418');
        self::assertSame($bankInfo, $viaBankCode);
        self::assertSame(1, $spy->calls, 'findByIban() and findByBankCode() must share the same cache entry for the same natural key.');
    }

    public function testDistinctBankOrBranchCodesUseDistinctCacheEntries(): void
    {
        $bankInfoA = $this->fixedBankInfo();
        $bankInfoB = new BankInfo(
            bankName: 'Other Bank',
            shortName: 'OB',
            bic: 'OTHRESBBXXX',
            city: 'Madrid',
            address: null,
            sepaSct: null,
            sepaSctInst: null,
            sepaSddCore: null,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );

        $spy = new class ($bankInfoA, $bankInfoB) implements ProviderInterface {
            public int $calls = 0;

            public function __construct(
                private readonly BankInfo $bankInfoA,
                private readonly BankInfo $bankInfoB,
            ) {
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
                $this->calls++;

                return $bankCode === '2100' ? $this->bankInfoA : $this->bankInfoB;
            }
        };

        $cached = new CachedProvider($spy, $this->makeInMemoryCache());

        self::assertSame($bankInfoA, $cached->findByBankCode('ES', '2100', '0418'));
        self::assertSame($bankInfoB, $cached->findByBankCode('ES', '0049', '0418'));
        self::assertSame(2, $spy->calls, 'Different bank codes must not collide on the same cache entry.');
    }

    public function testCacheKeyNormalizesCountryCodeCaseSoLookupsShareTheSameEntry(): void
    {
        $bankInfo = $this->fixedBankInfo();

        $spy = new class ($bankInfo) implements ProviderInterface {
            public int $calls = 0;

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
                $this->calls++;

                return $this->bankInfo;
            }
        };

        $cached = new CachedProvider($spy, $this->makeInMemoryCache());

        $cached->findByBankCode('es', '2100', '0418');
        $cached->findByBankCode('ES', '2100', '0418');

        self::assertSame(1, $spy->calls, 'Lower/upper-case country codes must resolve to the same cache entry.');
    }

    /**
     * Default `Config\Iban::$cacheTtl` (`null`) must leave `Services::iban()`'s
     * behavior unchanged: the resolver's provider stays unwrapped. See
     * `Config\Iban::$cacheTtl`'s docblock for the full null/0/>0 matrix
     * (BREAKING as of v1.6 -- `0` used to mean "disabled"; `null` does now).
     */
    public function testConfigIbanDefaultsCacheTtlToNull(): void
    {
        self::assertNull((new IbanConfig())->cacheTtl);
    }

    public function testServicesIbanDoesNotWrapTheProviderWhenCacheTtlIsNull(): void
    {
        config(IbanConfig::class)->provider = 'database';
        config(IbanConfig::class)->cacheTtl = null;

        $iban = IbanServices::iban(false);

        self::assertInstanceOf(DatabaseProvider::class, self::resolverProviderOf($iban));
    }

    /**
     * `cacheTtl = 0` is no longer "disabled" (that's `null` now) -- it wraps
     * the provider with a TTL of `0`, which CI4's cache handlers treat as
     * "never expires". {@see self::testATtlOfZeroIsForwardedToCacheSaveVerbatim()}
     * proves the `0` reaches `CacheInterface::save()` unchanged.
     */
    public function testServicesIbanWrapsTheDatabaseProviderInCachedProviderWhenCacheTtlIsZero(): void
    {
        config(IbanConfig::class)->provider = 'database';
        config(IbanConfig::class)->cacheTtl = 0;

        $iban = IbanServices::iban(false);

        $provider = self::resolverProviderOf($iban);
        self::assertInstanceOf(CachedProvider::class, $provider);
        self::assertSame(0, self::ttlOf($provider));
    }

    public function testServicesIbanWrapsTheDatabaseProviderInCachedProviderWhenCacheTtlIsPositive(): void
    {
        config(IbanConfig::class)->provider = 'database';
        config(IbanConfig::class)->cacheTtl = 300;

        $iban = IbanServices::iban(false);

        $provider = self::resolverProviderOf($iban);
        self::assertInstanceOf(CachedProvider::class, $provider);
        self::assertSame(300, self::ttlOf($provider));
    }

    public function testServicesIbanSkipsWrappingTheNullProviderEvenWhenCacheTtlIsZero(): void
    {
        config(IbanConfig::class)->provider = 'null';
        config(IbanConfig::class)->cacheTtl = 0;

        $iban = IbanServices::iban(false);

        self::assertInstanceOf(NullProvider::class, self::resolverProviderOf($iban));
    }

    public function testServicesIbanSkipsWrappingTheNullProviderEvenWhenCacheTtlIsPositive(): void
    {
        config(IbanConfig::class)->provider = 'null';
        config(IbanConfig::class)->cacheTtl = 300;

        $iban = IbanServices::iban(false);

        self::assertInstanceOf(NullProvider::class, self::resolverProviderOf($iban));
    }

    /**
     * CI4 cache-TTL semantics (see `CachedProvider`'s class docblock): a
     * `$ttl` of `0` passed to `CacheInterface::save()` means "never
     * expires", not "already expired"/"disabled". `CachedProvider` must
     * forward it to `save()` completely unchanged -- this is the crux of
     * the v1.6 breaking change, verified directly against a TTL-recording
     * cache double rather than the real filesystem/Redis handlers.
     */
    public function testATtlOfZeroIsForwardedToCacheSaveVerbatim(): void
    {
        $bankInfo = $this->fixedBankInfo();

        $spy = new class ($bankInfo) implements ProviderInterface {
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
                return $this->bankInfo;
            }
        };

        $ttlSpyCache = $this->makeTtlRecordingCache();

        $cached = new CachedProvider($spy, $ttlSpyCache, 0);
        $cached->findByBankCode('ES', '2100', '0418');

        self::assertSame([0], $ttlSpyCache->savedTtls);
    }

    public function testAPositiveTtlIsForwardedToCacheSaveVerbatim(): void
    {
        $bankInfo = $this->fixedBankInfo();

        $spy = new class ($bankInfo) implements ProviderInterface {
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
                return $this->bankInfo;
            }
        };

        $ttlSpyCache = $this->makeTtlRecordingCache();

        $cached = new CachedProvider($spy, $ttlSpyCache, 3600);
        $cached->findByBankCode('ES', '2100', '0418');

        self::assertSame([3600], $ttlSpyCache->savedTtls);
    }

    // -- findByBic ---------------------------------------------------------

    public function testFindByBicDelegatesToTheInnerProviderAndCachesTheHit(): void
    {
        $bankInfo = $this->fixedBankInfo();

        $spy = new class ($bankInfo) implements BicProviderInterface, ProviderInterface {
            public int $bicCalls = 0;

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
                $this->bicCalls++;

                return $this->bankInfo;
            }
        };

        $cached = new CachedProvider($spy, $this->makeInMemoryCache());

        self::assertSame($bankInfo, $cached->findByBic('CAIXESBBXXX'));
        self::assertSame(1, $spy->bicCalls);

        self::assertSame($bankInfo, $cached->findByBic('CAIXESBBXXX'));
        self::assertSame(1, $spy->bicCalls, 'The second identical BIC lookup must be served from cache, not re-query the inner provider.');
    }

    public function testFindByBicCachesAMissSoTheInnerProviderIsNotReQueried(): void
    {
        $spy = new class () implements BicProviderInterface, ProviderInterface {
            public int $bicCalls = 0;

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
                $this->bicCalls++;

                return null;
            }
        };

        $cached = new CachedProvider($spy, $this->makeInMemoryCache());

        self::assertNull($cached->findByBic('CAIXESBBXXX'));
        self::assertSame(1, $spy->bicCalls);

        self::assertNull($cached->findByBic('CAIXESBBXXX'), 'A cached BIC miss must still resolve to null, not the sentinel.');
        self::assertSame(1, $spy->bicCalls, 'A cached BIC miss must not re-query the inner provider.');
    }

    public function testFindByBicReturnsNullWhenInnerProviderIsNotBicCapable(): void
    {
        // A bare ProviderInterface spy (NO BicProviderInterface): the decorator
        // must return null WITHOUT consulting it at all.
        $spy = new class () implements ProviderInterface {
            public int $calls = 0;

            public function supports(string $countryCode): bool
            {
                $this->calls++;

                return true;
            }

            public function findByIban(ParsedIban $iban): ?BankInfo
            {
                $this->calls++;

                return null;
            }

            public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
            {
                $this->calls++;

                return null;
            }
        };

        $cached = new CachedProvider($spy, $this->makeInMemoryCache());

        self::assertNull($cached->findByBic('CAIXESBBXXX'));
        self::assertSame(0, $spy->calls, 'A non-BIC inner provider must not be consulted for a BIC lookup.');
    }

    /**
     * The BIC cache key lives in its OWN namespace (`iban_bic_…`), distinct
     * from the bank-code keys (`iban_bank_…`), so the two lookup families can
     * never collide in the cache. Proven directly against a key-recording
     * cache double.
     */
    public function testBicCacheKeyUsesADistinctNamespaceFromBankCodeKeys(): void
    {
        $bankInfo = $this->fixedBankInfo();

        $spy = new class ($bankInfo) implements BicProviderInterface, ProviderInterface {
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
                return $this->bankInfo;
            }

            public function findByBic(string $bic): BankInfo
            {
                return $this->bankInfo;
            }
        };

        $keyCache = $this->makeKeyRecordingCache();

        $cached = new CachedProvider($spy, $keyCache);
        $cached->findByBic('CAIXESBBXXX');
        $cached->findByBankCode('ES', '2100', '0418');

        self::assertCount(2, $keyCache->savedKeys);
        [$bicKey, $bankKey] = $keyCache->savedKeys;

        self::assertStringStartsWith('iban_bic_', $bicKey);
        self::assertStringStartsWith('iban_bank_', $bankKey);
        self::assertNotSame($bicKey, $bankKey);
    }

    public function testBicCacheKeyNormalizesCaseAndWhitespaceSoLookupsShareTheSameEntry(): void
    {
        $bankInfo = $this->fixedBankInfo();

        $spy = new class ($bankInfo) implements BicProviderInterface, ProviderInterface {
            public int $bicCalls = 0;

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
                $this->bicCalls++;

                return $this->bankInfo;
            }
        };

        $cached = new CachedProvider($spy, $this->makeInMemoryCache());

        $cached->findByBic(' caix esbb xxx ');
        $cached->findByBic('CAIXESBBXXX');

        self::assertSame(1, $spy->bicCalls, 'Case/whitespace variants of the same BIC must share one cache entry.');
    }

    private function fixedBankInfo(): BankInfo
    {
        return new BankInfo(
            bankName: 'CaixaBank',
            shortName: 'CX',
            bic: 'CAIXESBBXXX',
            city: 'Barcelona',
            address: null,
            sepaSct: true,
            sepaSctInst: null,
            sepaSddCore: true,
            sepaSddB2b: null,
            sourceId: null,
            sourceVersion: null,
            sourceLicense: null,
        );
    }

    private function parsedIban(string $countryCode, string $bankIdentifier, ?string $branchIdentifier): ParsedIban
    {
        return new ParsedIban(
            countryCode: $countryCode,
            checkDigits: '91',
            bban: '21000418450200051332',
            bankIdentifier: $bankIdentifier,
            branchIdentifier: $branchIdentifier,
            accountNumber: '450200051332',
            nationalCheckDigit: null,
            sepaCountry: true,
            electronic: 'ES9121000418450200051332',
        );
    }

    /**
     * In-memory `CacheInterface` test double: a real, deterministic
     * get/save store with no filesystem I/O or TTL-based expiry, so cache
     * hit/miss assertions above never depend on timing or leftover state
     * from a previous test run.
     */
    private function makeInMemoryCache(): CacheInterface
    {
        return new class () implements CacheInterface {
            /** @var array<string, mixed> */
            private array $store = [];

            public function initialize(): void
            {
            }

            public function get(string $key): mixed
            {
                return $this->store[$key] ?? null;
            }

            public function save(string $key, mixed $value, int $ttl = 60): bool
            {
                $this->store[$key] = $value;

                return true;
            }

            public function remember(string $key, int $ttl, Closure $callback): mixed
            {
                if (! array_key_exists($key, $this->store)) {
                    $this->store[$key] = $callback();
                }

                return $this->store[$key];
            }

            public function delete(string $key): bool
            {
                unset($this->store[$key]);

                return true;
            }

            public function deleteMatching(string $pattern): int
            {
                return 0;
            }

            public function increment(string $key, int $offset = 1): bool|int
            {
                return false;
            }

            public function decrement(string $key, int $offset = 1): bool|int
            {
                return false;
            }

            public function clean(): bool
            {
                $this->store = [];

                return true;
            }

            public function getCacheInfo(): array|false|object|null
            {
                return null;
            }

            public function getMetaData(string $key): ?array
            {
                return null;
            }

            public function isSupported(): bool
            {
                return true;
            }
        };
    }

    /**
     * Same in-memory `CacheInterface` test double as {@see self::makeInMemoryCache()},
     * plus a public `$savedTtls` log of every `$ttl` argument `save()` was
     * called with -- used to prove the exact TTL `Config\Iban::$cacheTtl`
     * configures (including `0`, meaning "never expires" in CI4) reaches the
     * cache handler unchanged (see {@see \Daycry\Iban\Providers\CachedProvider}).
     *
     * @return CacheInterface&object{savedTtls: list<int>}
     */
    private function makeTtlRecordingCache(): CacheInterface
    {
        return new class () implements CacheInterface {
            /** @var list<int> */
            public array $savedTtls = [];

            /** @var array<string, mixed> */
            private array $store = [];

            public function initialize(): void
            {
            }

            public function get(string $key): mixed
            {
                return $this->store[$key] ?? null;
            }

            public function save(string $key, mixed $value, int $ttl = 60): bool
            {
                $this->savedTtls[] = $ttl;
                $this->store[$key] = $value;

                return true;
            }

            public function remember(string $key, int $ttl, Closure $callback): mixed
            {
                if (! array_key_exists($key, $this->store)) {
                    $this->store[$key] = $callback();
                }

                return $this->store[$key];
            }

            public function delete(string $key): bool
            {
                unset($this->store[$key]);

                return true;
            }

            public function deleteMatching(string $pattern): int
            {
                return 0;
            }

            public function increment(string $key, int $offset = 1): bool|int
            {
                return false;
            }

            public function decrement(string $key, int $offset = 1): bool|int
            {
                return false;
            }

            public function clean(): bool
            {
                $this->store = [];

                return true;
            }

            public function getCacheInfo(): array|false|object|null
            {
                return null;
            }

            public function getMetaData(string $key): ?array
            {
                return null;
            }

            public function isSupported(): bool
            {
                return true;
            }
        };
    }

    /**
     * In-memory `CacheInterface` test double that additionally logs, in order,
     * every key `save()` was called with -- used to prove the BIC cache key
     * lives in a distinct namespace from the bank-code keys (no collision).
     *
     * @return CacheInterface&object{savedKeys: list<string>}
     */
    private function makeKeyRecordingCache(): CacheInterface
    {
        return new class () implements CacheInterface {
            /** @var list<string> */
            public array $savedKeys = [];

            /** @var array<string, mixed> */
            private array $store = [];

            public function initialize(): void
            {
            }

            public function get(string $key): mixed
            {
                return $this->store[$key] ?? null;
            }

            public function save(string $key, mixed $value, int $ttl = 60): bool
            {
                $this->savedKeys[]  = $key;
                $this->store[$key] = $value;

                return true;
            }

            public function remember(string $key, int $ttl, Closure $callback): mixed
            {
                if (! array_key_exists($key, $this->store)) {
                    $this->store[$key] = $callback();
                }

                return $this->store[$key];
            }

            public function delete(string $key): bool
            {
                unset($this->store[$key]);

                return true;
            }

            public function deleteMatching(string $pattern): int
            {
                return 0;
            }

            public function increment(string $key, int $offset = 1): bool|int
            {
                return false;
            }

            public function decrement(string $key, int $offset = 1): bool|int
            {
                return false;
            }

            public function clean(): bool
            {
                $this->store = [];

                return true;
            }

            public function getCacheInfo(): array|false|object|null
            {
                return null;
            }

            public function getMetaData(string $key): ?array
            {
                return null;
            }

            public function isSupported(): bool
            {
                return true;
            }
        };
    }

    /**
     * Reaches into the facade's private `Resolver::$provider` to assert
     * which provider class `Config\Services::iban()` actually wired up
     * (same helper pattern as `tests/Config/ServicesTest.php`).
     */
    private static function resolverProviderOf(IbanService $iban): object
    {
        $property = new ReflectionProperty(Resolver::class, 'provider');
        $property->setAccessible(true);

        return $property->getValue($iban->resolver());
    }

    /**
     * Reaches into `CachedProvider`'s private `$ttl` to assert which TTL
     * `Config\Services::iban()` actually configured it with, without
     * triggering a real cache/DB round-trip.
     */
    private static function ttlOf(CachedProvider $provider): int
    {
        $property = new ReflectionProperty(CachedProvider::class, 'ttl');
        $property->setAccessible(true);

        /** @var int $value */
        $value = $property->getValue($provider);

        return $value;
    }
}
