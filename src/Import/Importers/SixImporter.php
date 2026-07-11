<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Switzerland (CH): SIX Interbank Clearing's
 * "Bank Master" directory (IID/QR-IID registry), the authoritative list of
 * Swiss Interbank Clearing IIDs (this source's `bank_code`) backing Swiss
 * IBAN/QR-IBAN routing.
 *
 * - Live source: {@see self::sourceUrl()} -- the "Download Bank Master V3"
 *   CSV endpoint, confirmed live on 2026-07-11.
 * - Format: `;`-delimited CSV, UTF-8 (NOT Latin-1), CRLF line endings.
 * - **Header/data column-count mismatch**: the header row carries 22 fields
 *   (its last one is a trailing generation timestamp with no data-row
 *   counterpart), while every DATA row carries only 21. Unlike
 *   {@see OenbImporter}/{@see BundesbankImporter}'s header-driven mapping,
 *   {@see self::rows()} therefore does NOT `array_combine()` the header with
 *   each row, and does NOT require `count($fields) === count($header)` --
 *   the header line is read once and discarded, and every subsequent line is
 *   indexed by FIXED POSITION (0-based): `0`=IID/QR-IID, `2`=Concatenation,
 *   `8`=Name of bank/institution, `9`=Street Name, `10`=Building Number,
 *   `12`=Town Name, `14`=BIC.
 * - **IID zero-padding**: the source's IID column has NO leading zeros
 *   (`700`, `9000`, ...), but this source's `bank_code` position in a Swiss
 *   IBAN is fixed at 5 digits -- {@see self::rows()} left-pads every IID to
 *   5 digits with `str_pad(..., 5, '0', STR_PAD_LEFT)`. QR-IIDs (the
 *   30000-31999 range) are already 5 digits and pass through unchanged;
 *   they're imported like any other row since they're valid identifiers for
 *   QR-IBANs.
 * - **Merger-stub filter (Concatenation)**: rows whose `Concatenation`
 *   column (idx 2) is `'Y'` are placeholder/forwarding stubs for an IID that
 *   has been merged into another one (name/BIC/address all blank) -- they
 *   carry no usable bank data, so {@see self::rows()} skips them. Every IID
 *   is unique in this source (no head-office/branch dedup is needed, unlike
 *   OeNB/Bundesbank).
 * - Encoding: the source is valid UTF-8 (`Zürich`, `Genève`, ... are already
 *   correctly-encoded multibyte UTF-8) -- {@see self::rows()} only strips a
 *   defensive leading BOM; it must NOT Latin-1/Windows-1252-decode this
 *   source (unlike {@see BundesbankImporter}'s genuinely Latin-1 file).
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
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class SixImporter implements ImporterInterface
{
    private const INDEX_IID           = 0;
    private const INDEX_CONCATENATION = 2;
    private const INDEX_NAME          = 8;
    private const INDEX_STREET        = 9;
    private const INDEX_BUILDING_NR   = 10;
    private const INDEX_CITY          = 12;
    private const INDEX_BIC           = 14;

    private const MINIMUM_FIELD_COUNT = self::INDEX_BIC + 1;

    private const CONCATENATION_MERGED = 'Y';

    private const BANK_CODE_LENGTH = 5;

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
        $raw = $localFile !== null
            ? @file_get_contents($localFile)
            : @file_get_contents($this->sourceUrl());

        if ($raw === false || $raw === '') {
            return;
        }

        $raw = self::stripBom($raw);

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
        // data row (see class docblock).
        while (($fields = fgetcsv($stream, 0, ';')) !== false) {
            if ($fields === [null]) {
                continue; // blank line
            }

            if (count($fields) < self::MINIMUM_FIELD_COUNT) {
                continue; // malformed row: too short to carry a BIC field
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
                'name'        => self::nullableTrim($fields[self::INDEX_NAME] ?? ''),
                'city'        => self::nullableTrim($fields[self::INDEX_CITY] ?? ''),
                'bic'         => self::nullableTrim($fields[self::INDEX_BIC] ?? ''),
                'address'     => self::buildAddress(
                    $fields[self::INDEX_STREET] ?? '',
                    $fields[self::INDEX_BUILDING_NR] ?? '',
                ),
            ];
        }

        fclose($stream);
    }

    private static function nullableTrim(?string $value): ?string
    {
        $trimmed = trim($value ?? '');

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function buildAddress(?string $street, ?string $buildingNumber): ?string
    {
        $combined = trim(trim($street ?? '') . ' ' . trim($buildingNumber ?? ''));

        return $combined !== '' ? $combined : null;
    }

    /**
     * Strips a leading UTF-8 BOM if present. Unlike {@see OenbImporter}'s
     * `normalizeEncoding()`, no Windows-1252 fallback is applied here: this
     * source is confirmed UTF-8, and re-decoding already-valid UTF-8 bytes
     * (e.g. `Zürich`) as Windows-1252 would corrupt them.
     */
    private static function stripBom(string $raw): string
    {
        return str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
    }
}
