<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\ParsesSixBankMaster;

/**
 * Official-source importer for Switzerland (CH): SIX Interbank Clearing's
 * "Bank Master" directory (IID/QR-IID registry), the authoritative list of
 * Swiss Interbank Clearing IIDs (this source's `bank_code`) backing Swiss
 * IBAN/QR-IBAN routing.
 *
 * - Live source: {@see self::sourceUrl()} -- the "Download Bank Master V3"
 *   CSV endpoint, confirmed live on 2026-07-11.
 * - **This single file also lists Liechtenstein (LI) banks** (its `Country`
 *   column, idx 13, is `'CH'` or `'LI'`) -- {@see \Daycry\Iban\Import\Importers\LiechtensteinImporter}
 *   sources the SAME file/URL, filtered to `Country === 'LI'`. This importer
 *   filters to `Country === 'CH'` only, so an LI bank is never wrongly
 *   imported as a CH row. The shared fetch/parse/filter logic lives in
 *   {@see ParsesSixBankMaster}, used by both importers.
 * - Format, column layout, IID zero-padding, merger-stub filtering and
 *   encoding handling are all documented on {@see ParsesSixBankMaster}'s
 *   class docblock (the trait this importer delegates {@see self::rows()}
 *   to).
 *
 * CAVEAT: parsing targets the documented/observed "Bank Master V3" CSV
 * layout as of this release -- validate against the live official file
 * before production use, since SIX has also historically published legacy
 * Bank Master variants (fixed-width/Excel, Latin-1-encoded) that are NOT
 * this format, and any of these may change without notice.
 *
 * LICENSING: SIX states information in the Download Bank Master "may be
 * used freely", but does not grant explicit permission to republish the
 * file verbatim as an independent directory, and the BIC column is SWIFT
 * SCRL's property. The safe design followed here is: import offline for
 * *resolution* purposes and attribute to SIX -- do not redistribute the
 * imported BIC column as a standalone BIC directory.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`)
 * to fetch/parse, per {@see ImporterInterface}'s framework-free contract --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\Importers\LiechtensteinImporter
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class SixImporter implements ImporterInterface
{
    use ParsesSixBankMaster;

    private const COUNTRY_FILTER = 'CH';

    public function countryCode(): string
    {
        return 'CH';
    }

    public function sourceId(): string
    {
        return 'six';
    }

    public function sourceName(): string
    {
        return 'SIX Interbank Clearing';
    }

    public function license(): string
    {
        return 'SIX Interbank Clearing (free use)';
    }

    public function sourceUrl(): string
    {
        return 'https://api.six-group.com/api/epcd/bankmaster/v3/bankmaster_V3.csv';
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        return $this->parseSixBankMaster($localFile, $this->sourceUrl(), self::COUNTRY_FILTER);
    }
}
