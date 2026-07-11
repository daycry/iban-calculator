<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Greece (GR): the Hellenic Bank Association's
 * (HBA) HEBIC "list of credit institutions" -- an institution-level (not
 * per-branch) directory of every 3-digit bank code (this source's
 * `bank_code`, the segment a Greek IBAN's BBAN starts with) authorized to
 * operate in Greece.
 *
 * - Live source: {@see self::sourceUrl()}. Confirmed live on 2026-07-11 that
 *   this endpoint returns the data file directly (not an HTML landing
 *   page).
 * - Format: `;`-delimited, one row per credit institution (already
 *   bank-level -- there is no branch/head-office distinction to filter,
 *   unlike {@see OenbImporter}/{@see BundesbankImporter}), with a 2-row
 *   preamble: row 1 is a plain-text title with no delimiters ("LIST OF
 *   CREDIT INSTITUTIONS AUTHORIZED IN GREECE UNDER THE LAW 4261/2014,
 *   Directive 2013/36/EU"), row 2 is the column header (`Code Number;Name
 *   of Credit Institution;Address;Telephone;Fax;URL`).
 * - **Quoted bank code**: unlike a CSV-quoted field, this source wraps its
 *   `Code Number` column in literal SINGLE quotes as part of the cell value
 *   itself (e.g. `'011'`), not CSV double-quote enclosure -- `fgetcsv()`
 *   (default enclosure `"`) therefore yields the quotes as literal
 *   characters, which {@see self::rows()} strips explicitly.
 * - **Preamble filter**: rather than special-casing "skip the first 2
 *   lines", {@see self::rows()} uses a single robust rule -- a line is
 *   treated as DATA only if its first field (trimmed) matches
 *   `^'[0-9]+'$`; the title row's first (and only) field is the whole
 *   sentence above and the header row's first field is the literal text
 *   `"Code Number"`, so neither matches.
 * - Columns are matched by FIXED POSITION (0-based): `0`=quoted code number
 *   (`bank_code`), `1`=name. Only these two are mapped, per this source's
 *   bank-level scope -- address/telephone/fax/URL describe the institution's
 *   head office, not a specific branch, and are left unmapped here.
 * - Encoding: confirmed live to be **Windows-1253 (Greek)**, NOT UTF-8 --
 *   most rows are pure ASCII (bank names/addresses are published in Latin
 *   script), but some address cells embed genuine Greek-alphabet street
 *   names (e.g. "ΟΘΩΝΟΣ" for Othonos). {@see self::rows()} strips a
 *   defensive leading UTF-8 BOM, then falls back to a Windows-1253 -> UTF-8
 *   conversion when the raw bytes aren't already valid UTF-8 -- mirroring
 *   {@see OenbImporter}'s dual-encoding defensive pattern, with Windows-1253
 *   (not Windows-1252) as the fallback codepage since this is a Greek
 *   source. The fallback conversion uses `iconv()` rather than
 *   `mb_convert_encoding()`: PHP's bundled mbstring encoding tables don't
 *   include `Windows-1253` on every platform, while `iconv()` (backed by
 *   the system/bundled libiconv) does.
 *
 * CAVEAT: parsing targets the documented/observed source format as of this
 * release -- validate against the live official file before production use;
 * issuing bodies change layouts without notice.
 *
 * LICENSING: UNCERTAIN. HBA does not publish an explicit reuse/redistribution
 * license for this file alongside the download; {@see self::license()}
 * therefore names the source rather than asserting rights this package
 * cannot confirm it has. No data from this source is bundled with this
 * package -- the operator downloads/imports it themselves via `iban:update
 * --source=hba --country=GR --file=<csv>` (or live), so this uncertainty
 * doesn't affect what ships in this repository.
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
final class HellenicBankAssociationImporter implements ImporterInterface
{
    private const INDEX_CODE_NUMBER = 0;
    private const INDEX_NAME        = 1;

    private const CODE_NUMBER_PATTERN = '/^\'([0-9]+)\'$/';

    public function countryCode(): string
    {
        return 'GR';
    }

    public function sourceId(): string
    {
        return 'hba';
    }

    public function sourceName(): string
    {
        return 'Hellenic Bank Association (HEBIC)';
    }

    public function license(): string
    {
        return 'Hellenic Bank Association (HEBIC)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.hba.gr/En/Hebic/downloadbanks';
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

            $codeNumber = trim($fields[self::INDEX_CODE_NUMBER] ?? '');

            if (preg_match(self::CODE_NUMBER_PATTERN, $codeNumber, $matches) !== 1) {
                continue; // title/header preamble row, or malformed data
            }

            yield [
                'bank_code'   => $matches[1],
                'branch_code' => null,
                'name'        => self::nullableTrim($fields[self::INDEX_NAME] ?? ''),
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
     * Windows-1253 (Greek) -> UTF-8 conversion when the raw bytes aren't
     * valid UTF-8 (this source is confirmed to ship as Windows-1253, not
     * UTF-8 -- see the class docblock's ENCODING note). Uses `iconv()`
     * rather than `mb_convert_encoding()` since not every platform's
     * mbstring build ships a `Windows-1253` conversion table; if `iconv()`
     * itself fails (e.g. a genuinely malformed byte sequence), the raw
     * bytes are returned unconverted rather than throwing.
     */
    private static function normalizeEncoding(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $converted = @iconv('Windows-1253', 'UTF-8', $raw);
            $raw       = $converted !== false ? $converted : $raw;
        }

        return $raw;
    }
}
