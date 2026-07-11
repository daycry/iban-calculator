<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers\Concerns;

/**
 * Shared string-normalization helpers duplicated -- byte-identically, modulo
 * a `?string`-vs-`string` parameter signature drift -- across roughly two
 * dozen bank-directory importers under
 * {@see \Daycry\Iban\Import\Importers}. Centralizing them here removes that
 * drift and every private copy, mirroring how {@see ParsesSixBankMaster} /
 * {@see ReadsXlsxSource} already centralize this package's other
 * cross-importer duplication.
 *
 * - {@see self::nullableTrim()} unifies on a `?string` parameter, the union
 *   of both signatures importers previously declared (`nullableTrim(?string
 *   $value)` and `nullableTrim(string $value)`) -- every existing call site
 *   passed either a plain `string`, or `$fields[$i] ?? ''`/`(string) $x`
 *   already coercing to `string`, so accepting (and null-coalescing) `?string`
 *   here is a strict superset that changes no caller's observed behavior.
 * - {@see self::stripBom()} is the plain BOM-strip half shared by every CSV
 *   importer; {@see ReadsCsvSource::decodeCsvBytes()} builds on it for
 *   importers that also need a legacy-codepage fallback/conversion.
 *
 * FRAMEWORK-FREE: uses only native PHP (`trim()`, `substr()`,
 * `str_starts_with()`), per {@see \Daycry\Iban\Contracts\ImporterInterface}'s
 * framework-free contract -- even though `src/Import/` itself isn't guarded
 * by `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 */
trait NormalizesStrings
{
    /**
     * Trims `$value` (treating `null` as `''`) and maps a blank result to
     * `null`.
     */
    protected static function nullableTrim(?string $value): ?string
    {
        $trimmed = trim($value ?? '');

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * Strips a leading UTF-8 BOM (`EF BB BF`) if present, otherwise returns
     * `$raw` unchanged. No encoding conversion is performed here -- see
     * {@see ReadsCsvSource::decodeCsvBytes()} for importers that also need a
     * legacy-codepage fallback/conversion.
     */
    protected static function stripBom(string $raw): string
    {
        return str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
    }
}
