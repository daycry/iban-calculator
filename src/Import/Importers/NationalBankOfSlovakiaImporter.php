<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Slovakia (SK): the National Bank of
 * Slovakia's (Národná banka Slovenska, NBS) "Prevodník identifikačných
 * kódov SR" (SR identification code converter) -- the authoritative
 * registry of every payment-system code ("kód platobného styku", this
 * source's `bank_code`) backing Slovak IBAN/SIPS routing.
 *
 * - Live source: {@see self::sourceUrl()}, confirmed live on 2026-07-11.
 * - Format: `;`-delimited CSV, CRLF line endings, with a 2-row preamble: row
 *   1 is a merged-cell title ("Prevodník identifikačných kódov SR;;;"), row
 *   2 is the column header (`Kód platobného styku;Poskytovateľ platobných
 *   služieb;SWIFT kód 8;Systém SIPS`). Columns are matched by FIXED POSITION
 *   (0-based): `0`=payment-system code, `1`=name, `2`=BIC, `3`=a SIPS
 *   participation flag this importer doesn't need.
 * - **Unpadded bank code**: unlike this source's `bank_code` position in a
 *   Slovak IBAN, which is fixed at 4 digits, the source publishes the code
 *   WITHOUT leading zeros (`200`, `900`, `1100`, ...). {@see self::rows()}
 *   therefore left-pads every code to 4 digits with
 *   `str_pad(..., 4, '0', STR_PAD_LEFT)` (`200` -> `'0200'`, `1200`
 *   stays `'1200'`).
 * - **Preamble filter**: rather than special-casing "skip the first 2
 *   lines", {@see self::rows()} uses a single robust rule -- a line is
 *   treated as DATA only if its first field (trimmed) consists purely of
 *   digits (`^[0-9]+$`); the title row's first field is descriptive Slovak
 *   text and the header row's first field is the literal text `"Kód
 *   platobného styku"`, so neither matches.
 * - Already bank-level, one row per code -- there is no branch/head-office
 *   distinction to filter (unlike
 *   {@see OenbImporter}/{@see BundesbankImporter}), and Slovak IBANs carry
 *   no branch segment (`branch_code` stays `null` here). Some codes repeat
 *   the same institution under a legacy/"dobeh platieb" (payment wind-down)
 *   label with a different code (e.g. distinct codes for the same bank
 *   pre-/post-merger) -- each is imported as its own row, since each is a
 *   distinct valid `bank_code` an IBAN can carry.
 * - Encoding: confirmed live to be **Windows-1250 (Central European)**, NOT
 *   UTF-8 -- Slovak names routinely contain Á/Č/Ľ/Ň/Š/Ť/Ž (e.g.
 *   "Všeobecná úverová banka", "Slovenská sporiteľňa").
 *   {@see self::rows()} strips a defensive leading UTF-8 BOM, then falls
 *   back to a Windows-1250 -> UTF-8 conversion when the raw bytes aren't
 *   already valid UTF-8 -- mirroring {@see OenbImporter}'s dual-encoding
 *   defensive pattern. The fallback conversion uses `iconv()` rather than
 *   `mb_convert_encoding()`: PHP's bundled mbstring encoding tables don't
 *   include `Windows-1250` on every platform, while `iconv()` (backed by
 *   the system/bundled libiconv) does.
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
final class NationalBankOfSlovakiaImporter implements ImporterInterface
{
    private const INDEX_BANK_CODE = 0;
    private const INDEX_NAME      = 1;
    private const INDEX_BIC       = 2;

    private const BANK_CODE_PATTERN = '/^[0-9]+$/';
    private const BANK_CODE_LENGTH  = 4;

    public function countryCode(): string
    {
        return 'SK';
    }

    public function sourceId(): string
    {
        return 'nbs';
    }

    public function sourceName(): string
    {
        return 'National Bank of Slovakia';
    }

    public function license(): string
    {
        return 'National Bank of Slovakia';
    }

    public function sourceUrl(): string
    {
        return 'https://www.nbs.sk/_img/documents/_platobnesystemy/eurosips/prevodnik_ik_tps_sr.csv';
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

            $bankCode = trim($fields[self::INDEX_BANK_CODE] ?? '');

            if (preg_match(self::BANK_CODE_PATTERN, $bankCode) !== 1) {
                continue; // title/header preamble row, or malformed data
            }

            yield [
                'bank_code'   => str_pad($bankCode, self::BANK_CODE_LENGTH, '0', STR_PAD_LEFT),
                'branch_code' => null,
                'name'        => self::nullableTrim($fields[self::INDEX_NAME] ?? ''),
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
