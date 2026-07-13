<?php

declare(strict_types=1);

namespace Daycry\Iban\Providers;

use CodeIgniter\Cache\CacheInterface;
use Daycry\Iban\Contracts\ProviderInterface;
use Daycry\Iban\DTO\BankInfo;
use Daycry\Iban\DTO\ParsedIban;

/**
 * {@see ProviderInterface} decorator that caches bank-code lookups behind a
 * CI4 {@see CacheInterface}, so repeated identical lookups don't re-query
 * the decorated (inner) provider -- e.g. a {@see DatabaseProvider} hitting
 * the `banks` table on every {@see \Daycry\Iban\Resolver\Resolver::resolve()}
 * call for the same IBAN/bank code.
 *
 * Opt-in: {@see \Daycry\Iban\Config\Services::iban()} only wraps the
 * resolved provider in this decorator when
 * {@see \Daycry\Iban\Config\Iban::$cacheTtl} is non-`null`. The default
 * `null` leaves the resolver's provider unwrapped, so existing behavior is
 * unchanged unless a consuming app opts in.
 *
 * **`$ttl` of `0` means "never expires" (CI4 semantics), not "disabled"**:
 * once this decorator is constructed at all, its `$ttl` is forwarded
 * verbatim to `CacheInterface::save()`. CI4's bundled cache handlers treat a
 * `save()` TTL of `0` as "never expires" (e.g. `FileHandler::getMetaData()`:
 * `'expire' => $data['ttl'] > 0 ? ... : null`; `RedisHandler::save()` skips
 * `expireAt()` entirely when `$ttl === 0`). The "caching disabled" case is
 * handled one layer up, by {@see \Daycry\Iban\Config\Services::iban()} never
 * constructing this decorator when {@see \Daycry\Iban\Config\Iban::$cacheTtl}
 * is `null` -- by the time a `CachedProvider` exists, `0` always means
 * "cache forever", never "don't cache".
 *
 * **Warning -- misses are cached forever too when `$ttl === 0`**: see the
 * "Miss sentinel" note below. A never-expiring cache means a "not found"
 * result never expires either. Concretely: after running `php spark
 * iban:update` to import bank data, any IBAN/bank code that was a cached
 * miss BEFORE the import stays a permanent miss afterward unless the cache
 * is cleared -- run `php spark cache:clear` after every `iban:update` when
 * `$cacheTtl` is `0` (or any TTL long enough to outlive your update cadence).
 *
 * **Miss sentinel**: misses (`$inner->findByBankCode()` returning `null`)
 * are cached too, via {@see self::MISS}, so repeated lookups of a bank code
 * that doesn't exist don't keep re-querying the inner provider. This is
 * necessary because CI4's `CacheInterface::get()` returns `null` both when
 * a key was never stored AND when the value stored under it *is* `null` --
 * storing the literal `null` for a miss would therefore be indistinguishable
 * from "not cached yet" on the next lookup, permanently defeating the miss
 * cache. Storing a non-null sentinel instead lets a `null` `get()` result
 * unambiguously mean "not cached", while the sentinel means "cached miss".
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class CachedProvider implements ProviderInterface
{
    /**
     * Sentinel stored in place of `null` to cache a miss (see class
     * docblock). Never leaks to callers: {@see self::findByBankCode()} maps
     * it back to `null` before returning.
     */
    private const MISS = '__iban_miss__';

    public function __construct(
        private readonly ProviderInterface $inner,
        private readonly CacheInterface $cache,
        private readonly int $ttl = 3600,
        private readonly string $prefix = 'iban_bank_',
    ) {
    }

    public function supports(string $countryCode): bool
    {
        return $this->inner->supports($countryCode);
    }

    public function findByBankCode(string $countryCode, string $bankCode, ?string $branchCode = null): ?BankInfo
    {
        $key = $this->key($countryCode, $bankCode, $branchCode);

        $cached = $this->cache->get($key);

        if ($cached instanceof BankInfo) {
            return $cached;
        }

        if ($cached !== null) {
            // Any other non-null cached value is the self::MISS sentinel.
            return null;
        }

        $info = $this->inner->findByBankCode($countryCode, $bankCode, $branchCode);

        $this->cache->save($key, $info ?? self::MISS, $this->ttl);

        return $info;
    }

    /**
     * Delegates to {@see self::findByBankCode()} (this decorator's own
     * method, not the inner provider's `findByIban()`) so both entry points
     * share the exact same cache key/entry -- consistent with
     * {@see DatabaseProvider::findByIban()}, which does the same against
     * its own `findByBankCode()`.
     */
    public function findByIban(ParsedIban $iban): ?BankInfo
    {
        return $this->findByBankCode($iban->countryCode, $iban->bankIdentifier, $iban->branchIdentifier);
    }

    /**
     * Builds a sanitized cache key from the natural lookup key. CI4 cache
     * handlers reject certain characters (see `Config\Cache::$reservedCharacters`);
     * bank/branch codes are already `[A-Z0-9]` in practice by the time they
     * reach a provider (see {@see \Daycry\Iban\Resolver\Resolver}), but this
     * normalizes defensively so a stray reserved character never reaches
     * the cache handler.
     */
    private function key(string $countryCode, string $bankCode, ?string $branchCode): string
    {
        $raw = sprintf('%s%s_%s_%s', $this->prefix, strtoupper($countryCode), $bankCode, $branchCode ?? '');

        return preg_replace('/[^A-Za-z0-9_]/', '_', $raw) ?? $raw;
    }
}
