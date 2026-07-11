<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Germany (DE): the Deutsche Bundesbank's
 * "Bankleitzahlendatei" (bank code master file), the authoritative registry
 * of every valid German Bankleitzahl (BLZ). Published quarterly (the Monday
 * after the first Saturday of March/June/September/December) as a
 * fixed-width ASCII file, "unformatted"/uncompressed TXT variant.
 *
 * - Live source: {@see self::sourceUrl()} points at the currently-valid
 *   quarterly TXT download; the underlying blob URL rotates every quarter,
 *   so if it 404s, get the current link from the Bundesbank's download
 *   page: https://www.bundesbank.de/de/aufgaben/unbarer-zahlungsverkehr/serviceangebot/bankleitzahlen/download-bankleitzahlen-602592
 * - Format: one fixed-width record per line, 168 characters (basic 13-field
 *   file; the "erweiterte" 174-char variant appends a 14th IBAN-rule field
 *   this importer doesn't need), per the official "Merkblatt
 *   Bankleitzahlendatei" (Stand: 20. Dezember 2022), Anhang 1 -- positions
 *   are 1-based in that document, translated to 0-based `substr()` offsets
 *   below:
 *
 *   | Field | Content (German)                          | Length | Position (1-based) |
 *   |-------|--------------------------------------------|--------|---------------------|
 *   | 1     | Bankleitzahl (BLZ)                         | 8      | 1-8                 |
 *   | 2     | Merkmal (bank-code-bearing '1' / not '2')  | 1      | 9                   |
 *   | 3     | Bezeichnung (name, no legal form)          | 58     | 10-67               |
 *   | 4     | Postleitzahl (PLZ)                         | 5      | 68-72               |
 *   | 5     | Ort (city)                                 | 35     | 73-107              |
 *   | 6     | Kurzbezeichnung mit Ort (short name + city) | 27     | 108-134             |
 *   | 7     | Institutsnummer für PAN                    | 5      | 135-139             |
 *   | 8     | Business Identifier Code (BIC)             | 11     | 140-150             |
 *   | 9-13  | (check-digit method, record number, ...)   | -      | 151-168             |
 *
 * - Principal-only filter (Merkmal): per the Merkblatt, exactly one record
 *   per BLZ carries Merkmal `'1'` ("bankleitzahlführender
 *   Zahlungsdienstleister") -- that is the record relevant for payment
 *   routing/SEPA reachability. The same BLZ can additionally appear with
 *   Merkmal `'2'` for other branches/cities of the same institution (used
 *   only for location-based lookup, not payments). Since `banks` is keyed
 *   on `(country_code, bank_code, branch_code)` and German IBANs carry no
 *   branch segment (`branch_code` stays `null` here), importing every
 *   Merkmal-`'2'` row for the same BLZ would just have the last one
 *   silently win the upsert. {@see self::rows()} therefore imports ONLY
 *   Merkmal `'1'` records -- one row per BLZ, matching the Bundesbank's own
 *   "these are the records to use for payments" guidance.
 *
 * CAVEAT: parsing targets the documented source format as of this release;
 * validate against the live official file before production use -- issuing
 * bodies change layouts without notice.
 *
 * ENCODING: the Bankleitzahlendatei is published in **ISO-8859-1
 * (Latin-1)**, not UTF-8 -- German bank/city names routinely contain
 * umlauts (ä/ö/ü/ß). Because the record layout above is fixed-WIDTH BY BYTE
 * position in that source encoding (every Latin-1 char is exactly 1 byte),
 * {@see self::rows()} deliberately does NOT convert the whole line to UTF-8
 * before slicing fields: doing so would turn each umlaut into a 2-byte UTF-8
 * sequence and shift every subsequent `substr()` offset, corrupting the
 * parse. The correct order, applied here, is: (1) `substr()` the fields on
 * the RAW Latin-1 bytes -- offsets stay correct -- then (2) convert EACH
 * extracted text field (`name`/`city`/`short_name`) from ISO-8859-1 to UTF-8
 * individually, after extraction, then `trim()`. `bank_code` (BLZ) and
 * `bic` are pure ASCII, so converting them too is a harmless no-op; it's
 * applied uniformly for consistency. The `MINIMUM_LINE_LENGTH`/Merkmal
 * checks still operate on the raw bytes, which is correct since those are
 * also ASCII.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `substr()`,
 * `mb_convert_encoding()`) to fetch/parse, per {@see ImporterInterface}'s
 * framework-free contract -- even though `src/Import/` itself isn't guarded
 * by `tests/Architecture/CoreIsFrameworkFreeTest.php`. Requires `ext-mbstring`
 * (declared in `composer.json`).
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class BundesbankImporter implements ImporterInterface
{
    private const OFFSET_BLZ             = 0;
    private const LENGTH_BLZ             = 8;
    private const OFFSET_MERKMAL         = 8;
    private const LENGTH_MERKMAL         = 1;
    private const OFFSET_BEZEICHNUNG     = 9;
    private const LENGTH_BEZEICHNUNG     = 58;
    private const OFFSET_ORT             = 72;
    private const LENGTH_ORT             = 35;
    private const OFFSET_KURZBEZEICHNUNG = 107;
    private const LENGTH_KURZBEZEICHNUNG = 27;
    private const OFFSET_BIC             = 139;
    private const LENGTH_BIC             = 11;

    private const MINIMUM_LINE_LENGTH = self::OFFSET_BIC + self::LENGTH_BIC;

    private const MERKMAL_PRINCIPAL = '1';

    public function countryCode(): string
    {
        return 'DE';
    }

    public function sourceId(): string
    {
        return 'bundesbank';
    }

    public function sourceName(): string
    {
        return 'Deutsche Bundesbank';
    }

    public function license(): string
    {
        return 'Deutsche Bundesbank';
    }

    public function sourceUrl(): string
    {
        return 'https://www.bundesbank.de/resource/blob/602632/cef2676649334c0b78e3844783668462/472B63F073F071307366337C94F8C870/blz-aktuell-txt-data.txt';
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

        $lines = preg_split('/\r\n|\r|\n/', $raw);

        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            if (strlen($line) < self::MINIMUM_LINE_LENGTH) {
                continue; // blank/short/malformed line
            }

            $merkmal = substr($line, self::OFFSET_MERKMAL, self::LENGTH_MERKMAL);

            if ($merkmal !== self::MERKMAL_PRINCIPAL) {
                continue; // subordinate record for the same BLZ (see class docblock)
            }

            $bankCode = trim(self::fromLatin1(substr($line, self::OFFSET_BLZ, self::LENGTH_BLZ)));

            if ($bankCode === '') {
                continue;
            }

            $bic = trim(self::fromLatin1(substr($line, self::OFFSET_BIC, self::LENGTH_BIC)));

            yield [
                'bank_code'   => $bankCode,
                'branch_code' => null,
                'bic'         => $bic !== '' ? $bic : null,
                'name'        => self::nullableTrim(self::fromLatin1(substr($line, self::OFFSET_BEZEICHNUNG, self::LENGTH_BEZEICHNUNG))),
                'short_name'  => self::nullableTrim(self::fromLatin1(substr($line, self::OFFSET_KURZBEZEICHNUNG, self::LENGTH_KURZBEZEICHNUNG))),
                'city'        => self::nullableTrim(self::fromLatin1(substr($line, self::OFFSET_ORT, self::LENGTH_ORT))),
            ];
        }
    }

    private static function nullableTrim(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Converts a single already-extracted (fixed-width `substr()`'d) field
     * from the source's ISO-8859-1 (Latin-1) encoding to UTF-8. Must only be
     * called AFTER byte-offset slicing -- see the class docblock's ENCODING
     * note for why converting the whole line first would corrupt every
     * subsequent field offset.
     */
    private static function fromLatin1(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }
}
