<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Czechia (CZ): the Czech National Bank's
 * (Česká národní banka, ČNB) "Kódy bank v ČR" (bank codes in the Czech
 * Republic) list, the authoritative registry of every 4-digit payment-system
 * code ("kód platebního styku", this source's `bank_code`) backing Czech
 * IBAN/CERTIS routing.
 *
 * - Live source: {@see self::sourceUrl()}, confirmed live on 2026-07-11.
 * - Format: `;`-delimited CSV, UTF-8 WITH a leading BOM, CRLF line endings,
 *   one header row: `Kód platebního styku;Poskytovatel platebních
 *   služeb;BIC kód (SWIFT);Systém CERTIS`. Columns are matched by FIXED
 *   POSITION (0-based): `0`=bank code, `1`=name, `2`=BIC, `3`=a CERTIS
 *   participation flag this importer doesn't need.
 * - **Header/preamble filter**: rather than special-casing "skip the first
 *   line", {@see self::rows()} uses a single robust rule -- a line is
 *   treated as DATA only if its bank-code column (idx 0) matches
 *   `^[0-9]{4}$`; the header row's idx 0 is the literal text `"Kód
 *   platebního styku"`, which doesn't match, so it's skipped without a
 *   separate line-count special case.
 * - No head-office/branch dedup is needed here (unlike
 *   {@see OenbImporter}/{@see BundesbankImporter}): every code in this list
 *   is already a single, unique payment-system code with no per-branch
 *   repetition, and Czech IBANs carry no branch segment (`branch_code`
 *   stays `null` here).
 * - Some rows publish no BIC at all (e.g. building societies without their
 *   own SWIFT connectivity) -- `bic` is left `null` for those, which is
 *   valid per {@see ImporterInterface::rows()}'s `bic: string|null,
 *   optional` contract.
 * - Encoding: confirmed live to be genuine UTF-8 WITH a BOM -- unlike
 *   {@see OenbImporter}'s defensive Windows-1252 fallback (for a source
 *   whose encoding has historically varied), this source's encoding is
 *   settled, so {@see self::rows()} only strips the leading BOM.
 *
 * CAVEAT: parsing targets the documented/observed source format as of this
 * release -- validate against the live official file before production use;
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
final class CzechNationalBankImporter implements ImporterInterface
{
    private const INDEX_BANK_CODE = 0;
    private const INDEX_NAME      = 1;
    private const INDEX_BIC       = 2;

    private const BANK_CODE_PATTERN = '/^[0-9]{4}$/';

    public function countryCode(): string
    {
        return 'CZ';
    }

    public function sourceId(): string
    {
        return 'cnb';
    }

    public function sourceName(): string
    {
        return 'Czech National Bank';
    }

    public function license(): string
    {
        return 'Czech National Bank (cite source, no changes)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.cnb.cz/cs/platebni-styk/.galleries/ucty_kody_bank/download/kody_bank_CR.csv';
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

        while (($fields = fgetcsv($stream, 0, ';')) !== false) {
            if ($fields === [null]) {
                continue; // blank line
            }

            $bankCode = trim($fields[self::INDEX_BANK_CODE] ?? '');

            if (preg_match(self::BANK_CODE_PATTERN, $bankCode) !== 1) {
                continue; // header row, or malformed data
            }

            yield [
                'bank_code'   => $bankCode,
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
     * Strips a leading UTF-8 BOM if present (this source always ships one).
     * No Latin-1 fallback: this source is confirmed genuine UTF-8.
     */
    private static function stripBom(string $raw): string
    {
        return str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
    }
}
