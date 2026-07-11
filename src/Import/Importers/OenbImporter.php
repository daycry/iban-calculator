<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Austria (AT): the Oesterreichische
 * Nationalbank's (OeNB) "Bankstellenverzeichnis" (bank branch directory),
 * published as a semicolon-delimited CSV and also cataloged on data.gv.at
 * (Open Government Data Austria) under a CC-BY-4.0 license.
 *
 * - Live source: {@see self::sourceUrl()} --
 *   https://www.oenb.at/docroot/downloads_observ/bankstellenverzeichnis.csv
 *   (also listed at https://www.data.gv.at/katalog/de/dataset/oenb_bankstellenverzeichnis).
 * - Format (confirmed live on 2026-07-10): `;`-delimited CSV, one header row
 *   plus one row per bank *branch* ("Bankstelle"), with this header:
 *   `Bankenname;Identnummer;Identnummer der Hauptanstalt;Bankleitzahl;Straße;
 *   PLZ;Ort;Kennzeichen;Sektor;Firmenbuchnummer;Postadresse / Straße;
 *   Postadresse / PLZ;Postadresse / Ort;Postfach;Bundesland;Telefon;Fax;
 *   E-Mail;Homepage;LEI`. Columns are matched by NAME (via the header row),
 *   not position, so a reordering of the source's columns doesn't silently
 *   scramble the mapping.
 * - Head-office-only filter: the same `Bankleitzahl` (BLZ, this source's
 *   `bank_code`) repeats once per physical branch ("Bankstelle") of a bank,
 *   distinguished by `Kennzeichen` (`'Hauptanstalt'` for the head office,
 *   `'Zweigstelle'` for a branch). Since `banks` is keyed on
 *   `(country_code, bank_code, branch_code)` and Austrian IBANs carry no
 *   branch segment (`branch_code` stays `null` here), importing every
 *   branch row for the same BLZ would just have the last one silently win
 *   the upsert. {@see self::rows()} therefore imports ONLY
 *   `Kennzeichen === 'Hauptanstalt'` rows -- one row per BLZ.
 * - No BIC column: unlike some other national registries, this CSV does not
 *   publish a BIC per branch (OeNB maintains BIC data separately, e.g. in
 *   its SEPA-Zahlungsverkehrs-Verzeichnis companion file) -- `bic` is
 *   therefore left unset/`null` here, which is valid per
 *   {@see ImporterInterface::rows()}'s `bic: string|null, optional` contract.
 * - Encoding: defensively strips a leading UTF-8 BOM and falls back to a
 *   Windows-1252 -> UTF-8 conversion if the raw bytes aren't valid UTF-8,
 *   since Austrian government CSV exports have historically shipped in
 *   either encoding.
 *
 * CAVEAT: parsing targets the documented/observed source format as of this
 * release; validate against the live official file before production use --
 * issuing bodies change layouts without notice.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`)
 * to fetch/parse, per {@see ImporterInterface}'s framework-free contract --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class OenbImporter implements ImporterInterface
{
    private const COLUMN_BANK_NAME  = 'Bankenname';
    private const COLUMN_BANK_CODE  = 'Bankleitzahl';
    private const COLUMN_STREET     = 'Straße';
    private const COLUMN_POSTCODE   = 'PLZ';
    private const COLUMN_CITY       = 'Ort';
    private const COLUMN_MARKER     = 'Kennzeichen';
    private const HEAD_OFFICE_VALUE = 'Hauptanstalt';

    public function countryCode(): string
    {
        return 'AT';
    }

    public function sourceId(): string
    {
        return 'oenb';
    }

    public function sourceName(): string
    {
        return 'Oesterreichische Nationalbank';
    }

    public function license(): string
    {
        return 'CC-BY-4.0 (OeNB)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.oenb.at/docroot/downloads_observ/bankstellenverzeichnis.csv';
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

        $header = fgetcsv($stream, 0, ';');

        if ($header === false || $header === [null]) {
            fclose($stream);

            return;
        }

        /** @var list<string> $header */
        $header = array_map(static fn (?string $column): string => trim($column ?? ''), $header);

        while (($fields = fgetcsv($stream, 0, ';')) !== false) {
            if ($fields === [null]) {
                continue; // blank line
            }

            if (count($fields) !== count($header)) {
                continue; // malformed row: column count doesn't match the header
            }

            /** @var list<string> $fields */
            $fields = array_map(static fn (?string $value): string => $value ?? '', $fields);

            /** @var array<string, string> $row */
            $row = array_combine($header, $fields);

            $marker = trim($row[self::COLUMN_MARKER] ?? '');

            if ($marker !== self::HEAD_OFFICE_VALUE) {
                continue; // branch ("Zweigstelle") row for a BLZ already covered by its head office
            }

            $bankCode = trim($row[self::COLUMN_BANK_CODE] ?? '');

            if ($bankCode === '') {
                continue;
            }

            yield [
                'bank_code'   => $bankCode,
                'branch_code' => null,
                'name'        => self::nullableTrim($row[self::COLUMN_BANK_NAME] ?? ''),
                'city'        => self::nullableTrim($row[self::COLUMN_CITY] ?? ''),
                'address'     => self::buildAddress(
                    $row[self::COLUMN_STREET] ?? '',
                    $row[self::COLUMN_POSTCODE] ?? '',
                    $row[self::COLUMN_CITY] ?? '',
                ),
            ];
        }

        fclose($stream);
    }

    private static function nullableTrim(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function buildAddress(string $street, string $postCode, string $city): ?string
    {
        $street       = trim($street);
        $postCodeCity = trim($postCode . ' ' . $city);

        $parts = array_filter([$street, $postCodeCity], static fn (string $part): bool => $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Strips a leading UTF-8 BOM if present, and falls back to a
     * Windows-1252 -> UTF-8 conversion when the raw bytes aren't valid UTF-8
     * (Austrian government CSV exports have shipped in either encoding).
     */
    private static function normalizeEncoding(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return $raw;
    }
}
