<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers\Concerns;

/**
 * Shared parser for SIX Interbank Clearing's "Bank Master V3" `;`-delimited
 * CSV, reused by every importer sourcing data from that single file --
 * {@see \Daycry\Iban\Import\Importers\SixImporter} (CH) and
 * {@see \Daycry\Iban\Import\Importers\LiechtensteinImporter} (LI). Both
 * countries' banks are listed in the SAME file, distinguished only by the
 * `Country` column (idx 13, value `'CH'` or `'LI'`) -- this trait centralizes
 * the fetch/parse/normalize logic once and lets each importer select its own
 * country via {@see self::parseSixBankMaster()}'s `$countryFilter` argument,
 * so the row-parsing rules (column layout, IID zero-padding, merger-stub
 * skip, UTF-8 handling) can never drift between the two importers.
 *
 * - Format: `;`-delimited CSV, UTF-8 (NOT Latin-1), CRLF line endings.
 * - **Header/data column-count mismatch**: the header row carries 22 fields
 *   (its last one is a trailing generation timestamp with no data-row
 *   counterpart), while every DATA row carries only 21 -- the header line is
 *   read once and discarded, and every subsequent line is indexed by FIXED
 *   POSITION (0-based): `0`=IID/QR-IID, `2`=Concatenation, `8`=Name of
 *   bank/institution, `9`=Street Name, `10`=Building Number, `12`=Town Name,
 *   `13`=Country, `14`=BIC.
 * - **Country filter**: rows whose `Country` column (idx 13) doesn't equal
 *   `$countryFilter` are skipped -- this is what keeps
 *   {@see \Daycry\Iban\Import\Importers\SixImporter} CH-only and
 *   {@see \Daycry\Iban\Import\Importers\LiechtensteinImporter} LI-only from
 *   the same shared file.
 * - **IID zero-padding**: the source's IID column has NO leading zeros
 *   (`700`, `9000`, `8810`, ...), but this source's `bank_code` position in a
 *   Swiss/Liechtenstein IBAN is fixed at 5 digits -- every IID is left-padded
 *   to 5 digits with `str_pad(..., 5, '0', STR_PAD_LEFT)`. QR-IIDs (the
 *   30000-31999 range) are already 5 digits and pass through unchanged.
 * - **Merger-stub filter (Concatenation)**: rows whose `Concatenation`
 *   column (idx 2) is `'Y'` are placeholder/forwarding stubs for an IID that
 *   has been merged into another one (name/BIC/address all blank) -- they
 *   carry no usable bank data and are skipped. Every IID is unique in this
 *   source (no head-office/branch dedup is needed).
 * - Encoding: the source is valid UTF-8 (`Zürich`, `Vaduz`, ... are already
 *   correctly-encoded multibyte UTF-8) -- only a defensive leading BOM is
 *   stripped; this source must NOT be Latin-1/Windows-1252-decoded.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`),
 * per {@see \Daycry\Iban\Contracts\ImporterInterface}'s framework-free
 * contract -- even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\Importers\SixImporter
 * @see \Daycry\Iban\Import\Importers\LiechtensteinImporter
 */
trait ParsesSixBankMaster
{
    private const INDEX_IID           = 0;
    private const INDEX_CONCATENATION = 2;
    private const INDEX_NAME          = 8;
    private const INDEX_STREET        = 9;
    private const INDEX_BUILDING_NR   = 10;
    private const INDEX_CITY          = 12;
    private const INDEX_COUNTRY       = 13;
    private const INDEX_BIC           = 14;

    private const MINIMUM_FIELD_COUNT = self::INDEX_BIC + 1;

    private const CONCATENATION_MERGED = 'Y';

    private const BANK_CODE_LENGTH = 5;

    /**
     * Fetches (from `$localFile` or `$sourceUrl`) and parses a SIX Bank
     * Master V3 CSV, yielding only the rows whose `Country` column equals
     * `$countryFilter` (case-sensitive, e.g. `'CH'` or `'LI'`).
     *
     * @return iterable<array<string, mixed>>
     */
    private function parseSixBankMaster(?string $localFile, string $sourceUrl, string $countryFilter): iterable
    {
        $raw = $localFile !== null
            ? @file_get_contents($localFile)
            : @file_get_contents($sourceUrl);

        if ($raw === false || $raw === '') {
            return;
        }

        $raw = self::stripSixBankMasterBom($raw);

        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            return;
        }

        fwrite($stream, $raw);
        rewind($stream);

        $header = fgetcsv($stream, 0, ';');

        if ($header === false) {
            fclose($stream);

            return;
        }

        // The header carries 22 fields (a trailing generation timestamp
        // with no data-row counterpart) while data rows carry 21 -- it's
        // read here only to advance past it; it is never combined with a
        // data row (see this trait's docblock).
        while (($fields = fgetcsv($stream, 0, ';')) !== false) {
            if ($fields === [null]) {
                continue; // blank line
            }

            if (count($fields) < self::MINIMUM_FIELD_COUNT) {
                continue; // malformed row: too short to carry a BIC field
            }

            $country = trim($fields[self::INDEX_COUNTRY] ?? '');

            if ($country !== $countryFilter) {
                continue; // belongs to the other country sharing this source file
            }

            $concatenation = trim($fields[self::INDEX_CONCATENATION] ?? '');

            if ($concatenation === self::CONCATENATION_MERGED) {
                continue; // merger/forwarding stub: no usable bank data
            }

            $iid = trim($fields[self::INDEX_IID] ?? '');

            if ($iid === '') {
                continue;
            }

            yield [
                'bank_code'   => str_pad($iid, self::BANK_CODE_LENGTH, '0', STR_PAD_LEFT),
                'branch_code' => null,
                'name'        => self::nullableSixTrim($fields[self::INDEX_NAME] ?? ''),
                'city'        => self::nullableSixTrim($fields[self::INDEX_CITY] ?? ''),
                'bic'         => self::nullableSixTrim($fields[self::INDEX_BIC] ?? ''),
                'address'     => self::buildSixAddress(
                    $fields[self::INDEX_STREET] ?? '',
                    $fields[self::INDEX_BUILDING_NR] ?? '',
                ),
            ];
        }

        fclose($stream);
    }

    private static function nullableSixTrim(?string $value): ?string
    {
        $trimmed = trim($value ?? '');

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function buildSixAddress(?string $street, ?string $buildingNumber): ?string
    {
        $combined = trim(trim($street ?? '') . ' ' . trim($buildingNumber ?? ''));

        return $combined !== '' ? $combined : null;
    }

    /**
     * Strips a leading UTF-8 BOM if present. No Windows-1252 fallback is
     * applied here: this source is confirmed UTF-8, and re-decoding
     * already-valid UTF-8 bytes (e.g. `Zürich`) as Windows-1252 would
     * corrupt them.
     */
    private static function stripSixBankMasterBom(string $raw): string
    {
        return str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
    }
}
