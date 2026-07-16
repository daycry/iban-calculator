<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\NormalizesStrings;
use Daycry\Iban\Import\Support\XlsxReader;
use RuntimeException;

/**
 * Official-source importer for Cyprus (CY): the Central Bank of Cyprus'
 * `CIs_and_EMIs_BICs_updated_<month>_<year>.xlsx` spreadsheet, which maps
 * each 3-digit "bank identifier used in IBAN" (this source's `bank_code`,
 * positions 5-7 of a Cypriot IBAN) to the institution's name and BIC. Credit
 * institutions occupy 001-129, EMIs 901-912.
 *
 * - Live source: the spreadsheet's filename carries a rotating date, so
 *   {@see self::rows()} first GETs the stable IBAN landing page
 *   ({@see self::sourceUrl()}), regex-extracts the
 *   `CIs_and_EMIs_BICs_updated_*.xlsx` href, then downloads that `.xlsx`. Both
 *   requests send a **browser User-Agent** -- the site's WAF answers 403 to a
 *   plain, header-less fetch. The newer `.xls` (legacy BIFF) edition is
 *   deliberately NOT used ({@see XlsxReader} only reads OOXML `.xlsx`).
 * - Offline: `iban:update --file=path/to.xlsx` reads that `.xlsx` directly
 *   (the tested/supported path -- the live scrape is never exercised by the
 *   test suite, same as every other importer's network path).
 * - Format: the first worksheet has a title preamble row, then a header row
 *   this importer locates by name ({@see self::locateHeader()}): the code
 *   column is the literal `Bank identifiers used in IBAN`; the BIC and name
 *   columns are matched by a `BIC` / `NAME`|`INSTITUTION` substring.
 * - `bank_code` = the code column, zero-padded to 3 digits (a spreadsheet
 *   that stored `002` as the number `2` is recovered). Non-numeric / >3-digit
 *   cells are skipped.
 * - `bic`: internal whitespace stripped, upper-cased; blank -> `null`.
 *
 * LICENSING: the CBC Terms of Use permit reuse with attribution; consumed
 * under this package's fetch-on-demand posture, attributed to the CBC.
 *
 * CAVEAT: parsing targets the documented/observed layout as of this release
 * (the code column label, and the rotating filename pattern) -- validate
 * against the live file before production use.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()` with a stream
 * context, `tempnam()`) plus {@see XlsxReader} (itself framework-free), per
 * {@see ImporterInterface}'s framework-free contract -- even though
 * `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 */
final class CentralBankOfCyprusImporter implements ImporterInterface
{
    use NormalizesStrings;

    private const HEADER_CODE = 'code';
    private const HEADER_NAME = 'name';
    private const HEADER_BIC  = 'bic';

    private const CODE_LABEL_NEEDLE = 'bank identifiers used in iban';

    private const CODE_PATTERN = '/^[0-9]{1,3}$/';

    private const BROWSER_USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    private const HREF_PATTERN = '/href=["\']([^"\']*CIs_and_EMIs_BICs_updated_[^"\']*\.xlsx)["\']/i';

    public function countryCode(): string
    {
        return 'CY';
    }

    public function sourceId(): string
    {
        return 'cbc';
    }

    public function sourceName(): string
    {
        return 'Central Bank of Cyprus';
    }

    public function license(): string
    {
        return 'Central Bank of Cyprus (Terms of Use, attribution)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.centralbank.cy/en/financial-market-infrastructures-payments/international-bank-account-number-iban';
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        $grid = $localFile !== null ? self::readXlsx($localFile) : $this->liveGrid();

        $header = self::locateHeader($grid);

        if ($header === null) {
            return;
        }

        [$headerRowIndex, $columns] = $header;

        for ($i = $headerRowIndex + 1, $count = count($grid); $i < $count; $i++) {
            $row  = $grid[$i];
            $code = trim($row[$columns[self::HEADER_CODE]] ?? '');

            if (preg_match(self::CODE_PATTERN, $code) !== 1) {
                continue; // blank/preamble/malformed row
            }

            yield [
                'bank_code'   => str_pad($code, 3, '0', STR_PAD_LEFT),
                'branch_code' => null,
                'name'        => self::nullableTrim($row[$columns[self::HEADER_NAME]] ?? ''),
                'bic'         => self::normalizeBic($row[$columns[self::HEADER_BIC]] ?? ''),
            ];
        }
    }

    /**
     * Scans `$grid` for the header row -- the row carrying the literal
     * "Bank identifiers used in IBAN" code column plus a BIC column and a
     * name/institution column -- returning its index and a
     * logical-key -> column-index map, or `null` if absent.
     *
     * @param list<list<string>> $grid
     *
     * @return array{0: int, 1: array<string, int>}|null
     */
    private static function locateHeader(array $grid): ?array
    {
        foreach ($grid as $rowIndex => $row) {
            $codeCol = null;
            $nameCol = null;
            $bicCol  = null;

            foreach ($row as $columnIndex => $cell) {
                $normalized = strtolower(trim((string) preg_replace('/\s+/u', ' ', $cell)));

                if (str_contains($normalized, self::CODE_LABEL_NEEDLE)) {
                    $codeCol = $columnIndex;
                } elseif ($bicCol === null && str_contains($normalized, 'bic')) {
                    $bicCol = $columnIndex;
                } elseif ($nameCol === null && (str_contains($normalized, 'name') || str_contains($normalized, 'institution'))) {
                    $nameCol = $columnIndex;
                }
            }

            if ($codeCol !== null && $nameCol !== null && $bicCol !== null) {
                return [$rowIndex, [
                    self::HEADER_CODE => $codeCol,
                    self::HEADER_NAME => $nameCol,
                    self::HEADER_BIC  => $bicCol,
                ]];
            }
        }

        return null;
    }

    /**
     * Live path (NOT exercised by the test suite): GETs the landing page with
     * a browser User-Agent, extracts the dated `.xlsx` href, downloads it (again
     * with the User-Agent) and reads its first worksheet. Returns `[]` on any
     * fetch/parse failure.
     *
     * @return list<list<string>>
     */
    private function liveGrid(): array
    {
        $landing = self::fetchWithUserAgent($this->sourceUrl());

        if ($landing === '' || preg_match(self::HREF_PATTERN, $landing, $matches) !== 1) {
            return [];
        }

        $xlsxUrl = self::resolveUrl($this->sourceUrl(), $matches[1]);
        $bytes   = self::fetchWithUserAgent($xlsxUrl);

        if ($bytes === '') {
            return [];
        }

        $tmp = tempnam(sys_get_temp_dir(), 'iban_cy_xlsx_');

        if ($tmp === false) {
            return [];
        }

        try {
            file_put_contents($tmp, $bytes);

            return self::readXlsx($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * Reads the first worksheet of an `.xlsx` path, returning `[]` on parse
     * failure instead of throwing.
     *
     * @return list<list<string>>
     */
    private static function readXlsx(string $path): array
    {
        try {
            return XlsxReader::readFirstSheet($path);
        } catch (RuntimeException) {
            return [];
        }
    }

    /**
     * Fetches `$url` with a browser User-Agent (the CBC WAF rejects a
     * header-less request), returning `''` on failure.
     */
    private static function fetchWithUserAgent(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'header'          => 'User-Agent: ' . self::BROWSER_USER_AGENT . "\r\n",
                'follow_location' => 1,
                'timeout'         => 30,
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);

        return $raw === false ? '' : $raw;
    }

    /**
     * Resolves a possibly-relative href against the landing page URL.
     */
    private static function resolveUrl(string $base, string $href): string
    {
        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }

        $parts = parse_url($base);

        if (! isset($parts['scheme'], $parts['host'])) {
            return $href;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];

        if (str_starts_with($href, '/')) {
            return $origin . $href;
        }

        $path = isset($parts['path']) ? preg_replace('#/[^/]*$#', '/', $parts['path']) : '/';

        return $origin . ($path ?? '/') . $href;
    }

    /**
     * Strips internal whitespace from a BIC cell and maps blank to `null`.
     */
    private static function normalizeBic(string $value): ?string
    {
        $normalized = strtoupper((string) preg_replace('/\s+/u', '', $value));

        return $normalized !== '' ? $normalized : null;
    }
}
