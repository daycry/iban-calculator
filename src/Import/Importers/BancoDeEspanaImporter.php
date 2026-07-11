<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Spain (ES): Banco de España's Monetary
 * Financial Institutions (MFI) list, a machine-readable CSV publishing the
 * same 4-digit entity codes (this source's `bank_code` -- the segment a
 * Spanish IBAN's BBAN starts with) that back the "Registro de Entidades".
 *
 * - **Source note**: the literal "Registro de Entidades" is only available
 *   through an interactive portal / PDF export, with no clean, directly
 *   downloadable CSV. The machine-readable CSV that publishes the SAME
 *   4-digit entity codes and names is Banco de España's statistical
 *   **"Lista de Instituciones Financieras Monetarias" (MFI list)** --
 *   {@see self::sourceUrl()} -- which is what this importer consumes (via
 *   `--file=<csv>`).
 * - Format: `,`-delimited (COMMA) CSV, UTF-8 WITH a leading BOM
 *   (`EF BB BF`), 8 columns, some fields quoted (entity names routinely
 *   contain commas, e.g. `"Banco Santander, S.A."`) -- parsed with
 *   `fgetcsv()`, never a naive `explode()`/split.
 * - Columns are matched by FIXED POSITION (0-based) rather than by header
 *   name, since the ES/EN header label variants differ only in wording,
 *   not column order: `2`=NAME, `4`=ADDRESS, `7`=SUPERVISORY CODE
 *   (`bank_code`).
 * - **Money-market-fund filter**: this CSV also lists money-market funds,
 *   whose "SUPERVISORY CODE" is prefixed `FI` (e.g. `FI2680`) rather than
 *   being a plain 4-digit code. {@see self::rows()} keeps a row only when
 *   idx 7 matches `^[0-9]{4}$`, which drops those `FI####` rows. Banco de
 *   España's own code (`9000`) passes this filter like any other 4-digit
 *   code and is imported -- harmless, and documented here rather than
 *   special-cased. Codes are unique; no deduplication is needed.
 * - `bank_code` is kept as a STRING (never cast to `int`), so a leading
 *   zero (e.g. `"0049"`) is preserved.
 * - Encoding: the source is UTF-8 WITH a BOM (`España`, `Nicolás`,
 *   `A CORUÑA`, ... are already correctly-encoded multibyte UTF-8) --
 *   {@see self::rows()} strips the leading BOM (present at the very start
 *   of the raw bytes, before the header row) so it can never leak into a
 *   yielded field; it must NOT Latin-1-decode this source.
 * - Data reflects only currently-active entities -- de-registered entities
 *   (e.g. Banco Popular, `0075`) are absent, not tombstoned.
 *
 * CAVEAT: parsing targets the documented/observed MFI-list CSV layout as of
 * this release -- validate against the live official file before
 * production use; Banco de España could change the layout without notice.
 *
 * LICENSING: not a standard open-data license. Banco de España's legal
 * notice AUTHORIZES reproduction/distribution of this data subject to: (1)
 * always citing "Banco de España" as the source; (2) faithful
 * reproduction, without manipulation or alteration; (3) if resold/
 * transferred, disclosing that it is available free of charge on Banco de
 * España's website; (4) excluding any third-party content. UNCERTAINTY:
 * the "no alteration" clause is a gray area for reshaping this CSV into
 * `banks` rows -- reuse with attribution is intended here, but confirm
 * with Banco de España before any commercial redistribution.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`)
 * to fetch/parse, per {@see ImporterInterface}'s framework-free contract --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class BancoDeEspanaImporter implements ImporterInterface
{
    private const INDEX_NAME             = 2;
    private const INDEX_ADDRESS          = 4;
    private const INDEX_SUPERVISORY_CODE = 7;

    private const SUPERVISORY_CODE_PATTERN = '/^[0-9]{4}$/';

    public function countryCode(): string
    {
        return 'ES';
    }

    public function sourceId(): string
    {
        return 'bde';
    }

    public function sourceName(): string
    {
        return 'Banco de España';
    }

    public function license(): string
    {
        return 'Banco de España';
    }

    public function sourceUrl(): string
    {
        return 'https://www.bde.es/webbe/es/estadisticas/otras-clasificaciones/clasificacion-entidades/'
            . 'listas-instituciones-financieras/listas-instituciones-financieras-monetarias-pais/lista-mfi-es.csv';
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

        $header = fgetcsv($stream, 0, ',');

        if ($header === false) {
            fclose($stream);

            return;
        }

        while (($fields = fgetcsv($stream, 0, ',')) !== false) {
            if ($fields === [null]) {
                continue; // blank line
            }

            $supervisoryCode = trim($fields[self::INDEX_SUPERVISORY_CODE] ?? '');

            if (preg_match(self::SUPERVISORY_CODE_PATTERN, $supervisoryCode) !== 1) {
                continue; // e.g. a money-market fund's "FI####" code
            }

            yield [
                'bank_code'   => $supervisoryCode,
                'branch_code' => null,
                'name'        => self::nullableTrim($fields[self::INDEX_NAME] ?? ''),
                'address'     => self::nullableTrim($fields[self::INDEX_ADDRESS] ?? ''),
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
     * No Latin-1 fallback is applied: this source is UTF-8.
     */
    private static function stripBom(string $raw): string
    {
        return str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
    }
}
