<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\NormalizesStrings;

/**
 * Official-source importer for Portugal (PT): Banco de Portugal's SICOI
 * document "BIC associated with IBANs of PSPs participating in SICOI", which
 * maps each 4-digit `IBAN Bank Identifier` (this source's `bank_code`, the
 * first 4 digits of a Portuguese BBAN) to its PSP name and BIC (e.g. 0034 =
 * Caixa Geral de Depósitos / CGDIPTPL, 0033 = BNP Paribas / BNPAPTPL, 0035 =
 * Montepio / MPIOPTPL).
 *
 * OFFLINE `--file` ONLY. The authoritative source is a PDF, and this package
 * deliberately ships no PDF reader; the landing (`bportugal.pt/.../sicoi`)
 * also blocks bots (HTTP 403) and the document URL rotates by date. So a live,
 * no-`$localFile` call cannot resolve/parse the PDF on its own and gracefully
 * yields nothing (it fetches the raw `sourceUrl()` bytes -- a PDF, or a 403
 * body -- which contain no 4-digit-code data lines). The supported workflow is
 * `iban:update --source=bportugal --country=PT --file=<text>` with text the
 * operator pre-extracted themselves.
 *
 * EXTRACTION RECIPE (operator-side, documented so the `--file` input is
 * reproducible):
 *
 *   pdftotext -layout -enc UTF-8 "bic_linked_with_ibans_<date>.pdf" bportugal.txt
 *
 * `-layout` preserves the fixed-width column alignment this parser relies on;
 * `-enc UTF-8` keeps accented PSP names as UTF-8. If the operator omits
 * `-enc UTF-8` (older poppler defaults to Latin-1), accented names arrive as
 * Windows-1252 "mojibake" -- {@see self::decodeToUtf8()} repairs that by
 * converting any non-UTF-8 input from Windows-1252, so either extraction works.
 *
 * PARSING:
 * - The extracted text has a title/preamble line and a header row
 *   (`IBAN Bank Identifier | Payment Service Provider | BIC`) that carry no
 *   4-digit code and are therefore skipped.
 * - A DATA line starts with the 4-digit code, then the PSP name, then the BIC
 *   as the final whitespace-delimited token ({@see self::LINE_PATTERN}). The
 *   BIC is anchored at end-of-line as a well-formed BIC shape (6 letters + 2
 *   alnum + optional 3-char branch), so a name containing internal spaces is
 *   captured whole. A line with a code but no trailing BIC falls back to
 *   {@see self::CODE_NAME_PATTERN} (name only, `bic` = `null`), per
 *   {@see ImporterInterface::rows()}'s optional-`bic` contract.
 * - `bank_code` is kept as a 4-digit STRING so its leading zeros survive
 *   (every Portuguese code starts `00`). Name has its internal whitespace runs
 *   collapsed to single spaces and is trimmed.
 *
 * LICENSING: Banco de Portugal's reuse disclaimer permits reuse with
 * attribution; consumed under this package's fetch-on-demand/`--file` posture
 * (the data is not bundled).
 *
 * CAVEAT: parsing targets the documented/observed SICOI layout as of this
 * release (there is a 2026 edition) -- validate against a freshly-extracted
 * file before production use; the layout can change between editions.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `preg_*`,
 * `mb_*`), per {@see ImporterInterface}'s framework-free contract -- even
 * though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 */
final class BancoDePortugalImporter implements ImporterInterface
{
    use NormalizesStrings;

    /** Matches a data line: 4-digit code, name, then a trailing BIC. */
    private const LINE_PATTERN = '/^\s*(\d{4})\s+(.+?)\s+([A-Z]{6}[A-Z0-9]{2}(?:[A-Z0-9]{3})?)\s*$/u';

    /** Fallback for a code+name line that carries no BIC. */
    private const CODE_NAME_PATTERN = '/^\s*(\d{4})\s+(\S.*?)\s*$/u';

    public function countryCode(): string
    {
        return 'PT';
    }

    public function sourceId(): string
    {
        return 'bportugal';
    }

    public function sourceName(): string
    {
        return 'Banco de Portugal (SICOI)';
    }

    public function license(): string
    {
        return 'Banco de Portugal (attribution)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.bportugal.pt/en/page/sicoi';
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

        $text = self::decodeToUtf8($raw);

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $parsed = self::parseLine($line);

            if ($parsed === null) {
                continue; // preamble, header, or a non-data line
            }

            [$code, $name, $bic] = $parsed;

            yield [
                'bank_code'   => $code,
                'branch_code' => null,
                'name'        => $name,
                'bic'         => $bic,
            ];
        }
    }

    /**
     * Parses one extracted line into `[code, name, bic]`, or `null` if it
     * carries no 4-digit code (preamble/header/blank).
     *
     * @return array{0: string, 1: string|null, 2: string|null}|null
     */
    private static function parseLine(string $line): ?array
    {
        if (preg_match(self::LINE_PATTERN, $line, $m) === 1) {
            return [$m[1], self::cleanName($m[2]), $m[3]];
        }

        if (preg_match(self::CODE_NAME_PATTERN, $line, $m) === 1) {
            return [$m[1], self::cleanName($m[2]), null];
        }

        return null;
    }

    /**
     * Collapses internal whitespace runs (a `-layout` extraction can pad
     * between words) to single spaces and trims; blank maps to `null`.
     */
    private static function cleanName(string $value): ?string
    {
        return self::nullableTrim((string) preg_replace('/\s+/u', ' ', $value));
    }

    /**
     * Normalizes raw extracted bytes to valid UTF-8: strips a leading UTF-8
     * BOM, then -- if the bytes aren't already valid UTF-8 -- converts from
     * Windows-1252 (the "mojibake" case, an operator `pdftotext` run without
     * `-enc UTF-8`).
     */
    private static function decodeToUtf8(string $raw): string
    {
        $raw = self::stripBom($raw);

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return $raw;
    }
}
