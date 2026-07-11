<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Slovenia (SI): the Bank of Slovenia's (Banka
 * Slovenije, BSI) "List of identifiers of payment service providers" -- an
 * institution-level (not per-branch) directory of every 5-digit national
 * identifier (this source's `bank_code`, the segment a Slovenian IBAN's BBAN
 * starts with) assigned to a payment service provider operating in Slovenia.
 *
 * - Live source: {@see self::sourceUrl()}. Confirmed live on 2026-07-11 that
 *   this endpoint returns the CSV data directly (not an HTML landing page
 *   requiring a separate download link to be located).
 * - Format: `;`-delimited CSV, one header row confirmed live as `NATIONAL
 *   ID;NAME;ADDRESS;ZIP CODE;CITY;BIC11` (note: the ZIP column's header is
 *   literally `ZIP CODE`, two words). Columns are matched by FIXED POSITION
 *   (0-based): `0`=NATIONAL ID (`bank_code`), `1`=NAME, `2`=ADDRESS,
 *   `4`=CITY, `5`=BIC11. The `ZIP CODE` column (idx 3) is not mapped to any
 *   {@see ImporterInterface::rows()} key -- `address` is taken verbatim from
 *   the ADDRESS column alone, not concatenated with the postcode (unlike
 *   {@see OenbImporter}'s `buildAddress()`).
 * - Already bank-level, one row per provider -- there is no branch/
 *   head-office distinction to filter (unlike
 *   {@see OenbImporter}/{@see BundesbankImporter}), and Slovenian IBANs
 *   carry no branch segment (`branch_code` stays `null` here).
 * - **Header/preamble filter**: a line is treated as DATA only if its
 *   NATIONAL ID column (idx 0) matches `^[0-9]{5}$`; the header row's idx 0
 *   is the literal text `"NATIONAL ID"`, which doesn't match, so it's
 *   skipped without a separate line-count special case.
 * - Encoding: confirmed live to be **Windows-1250 (Central European)**, NOT
 *   UTF-8 -- Slovenian address/city names routinely contain Č/Š/Ž (e.g.
 *   "PRISTANIŠKA", "AMERIŠKA"). {@see self::rows()} strips a defensive
 *   leading UTF-8 BOM, then falls back to a Windows-1250 -> UTF-8 conversion
 *   when the raw bytes aren't already valid UTF-8 -- mirroring
 *   {@see OenbImporter}'s dual-encoding defensive pattern. The fallback
 *   conversion uses `iconv()` rather than `mb_convert_encoding()`: PHP's
 *   bundled mbstring encoding tables don't include `Windows-1250` on every
 *   platform, while `iconv()` (backed by the system/bundled libiconv) does.
 *
 * CAVEAT: parsing targets the documented/observed source format as of this
 * release -- validate against the live official file before production use;
 * issuing bodies change layouts without notice.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`,
 * `mb_check_encoding()`, `iconv()`) to fetch/parse, per
 * {@see ImporterInterface}'s framework-free contract -- even though
 * `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`. Requires `ext-mbstring`
 * and `ext-iconv` (declared in `composer.json`).
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class BankOfSloveniaImporter implements ImporterInterface
{
    private const INDEX_NATIONAL_ID = 0;
    private const INDEX_NAME        = 1;
    private const INDEX_ADDRESS     = 2;
    private const INDEX_CITY        = 4;
    private const INDEX_BIC         = 5;

    private const NATIONAL_ID_PATTERN = '/^[0-9]{5}$/';

    public function countryCode(): string
    {
        return 'SI';
    }

    public function sourceId(): string
    {
        return 'bsi';
    }

    public function sourceName(): string
    {
        return 'Bank of Slovenia';
    }

    public function license(): string
    {
        return 'Bank of Slovenia (cite source, no changes)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.bsi.si/en/d/list-of-identifiers-of-psp';
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        $raw = $localFile !== null
            ? @file_get_contents($localFile)
            : @file_get_contents($this->sourceUrl());

        if ($raw === false || $raw === '') {
            return;
        }

        $raw = self::normalizeEncoding($raw);

        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            return;
        }

        fwrite($stream, $raw);
        rewind($stream);

        while (($fields = fgetcsv($stream, 0, ';')) !== false) {
            if ($fields === [null]) {
                continue; // blank line
            }

            $nationalId = trim($fields[self::INDEX_NATIONAL_ID] ?? '');

            if (preg_match(self::NATIONAL_ID_PATTERN, $nationalId) !== 1) {
                continue; // header row, comment/preamble line, or malformed data
            }

            yield [
                'bank_code'   => $nationalId,
                'branch_code' => null,
                'name'        => self::nullableTrim($fields[self::INDEX_NAME] ?? ''),
                'address'     => self::nullableTrim($fields[self::INDEX_ADDRESS] ?? ''),
                'city'        => self::nullableTrim($fields[self::INDEX_CITY] ?? ''),
                'bic'         => self::nullableTrim($fields[self::INDEX_BIC] ?? ''),
            ];
        }

        fclose($stream);
    }

    private static function nullableTrim(?string $value): ?string
    {
        $trimmed = trim($value ?? '');

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Strips a leading UTF-8 BOM if present, and falls back to a
     * Windows-1250 (Central European) -> UTF-8 conversion when the raw
     * bytes aren't valid UTF-8 (this source is confirmed to ship as
     * Windows-1250, not UTF-8 -- see the class docblock's ENCODING note).
     * Uses `iconv()` rather than `mb_convert_encoding()` since not every
     * platform's mbstring build ships a `Windows-1250` conversion table; if
     * `iconv()` itself fails (e.g. a genuinely malformed byte sequence), the
     * raw bytes are returned unconverted rather than throwing.
     */
    private static function normalizeEncoding(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = @iconv('Windows-1250', 'UTF-8', $raw);
            $raw       = $converted !== false ? $converted : $raw;
        }

        return $raw;
    }
}
