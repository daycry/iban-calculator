<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\ParsesSixBankMaster;

/**
 * Official-source importer for Liechtenstein (LI): Liechtenstein's banks
 * participate in Swiss Interbank Clearing, so they are listed in the SAME
 * "Bank Master V3" file {@see SixImporter} (CH) sources -- distinguished
 * only by that file's `Country` column (idx 13, `'LI'` for this importer
 * vs. `'CH'` for {@see SixImporter}).
 *
 * - Live source: {@see self::sourceUrl()} -- the exact same "Download Bank
 *   Master V3" CSV endpoint {@see SixImporter} uses.
 * - Format, column layout, IID zero-padding, merger-stub filtering and
 *   encoding handling are all documented on {@see ParsesSixBankMaster}'s
 *   class docblock (the trait this importer delegates {@see self::rows()}
 *   to, shared with {@see SixImporter} to keep the two importers' parsing
 *   rules from ever drifting apart).
 * - `bank_code` is the 5-digit IID (zero-padded like {@see SixImporter}'s),
 *   e.g. IID `8810` (Liechtensteinische Landesbank AG) becomes `'08810'`,
 *   which is the bank-code segment of the example IBAN
 *   `LI21088100002324013AA`.
 *
 * CAVEAT: parsing targets the documented/observed "Bank Master V3" CSV
 * layout as of this release -- validate against the live official file
 * before production use; SIX may change this format without notice.
 *
 * LICENSING: see {@see SixImporter}'s class docblock -- the same licensing
 * caveat (free use, no explicit redistribution grant, BIC column is SWIFT
 * SCRL's property) applies to this importer since it's the same source file.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`)
 * to fetch/parse, per {@see ImporterInterface}'s framework-free contract --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see SixImporter
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class LiechtensteinImporter implements ImporterInterface
{
    use ParsesSixBankMaster;

    private const COUNTRY_FILTER = 'LI';

    public function countryCode(): string
    {
        return 'LI';
    }

    public function sourceId(): string
    {
        return 'six';
    }

    public function sourceName(): string
    {
        return 'SIX Interbank Clearing (Liechtenstein)';
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
