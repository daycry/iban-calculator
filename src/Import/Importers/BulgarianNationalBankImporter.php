<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\NormalizesStrings;
use SimpleXMLElement;

/**
 * Official-source importer for Bulgaria (BG): the Bulgarian National Bank's
 * (Българска народна банка, БНБ) "БАЕ кодове и BIC на банките" (BAE codes
 * and BIC of the banks) register -- the authoritative list backing Bulgarian
 * IBAN/BISERA routing.
 *
 * - Live source: {@see self::sourceUrl()}, confirmed live on 2026-07-11. The
 *   published filename is `BAE_BIC.xls`, but the bytes are NOT a real
 *   (binary) `.xls` -- they are Microsoft **SpreadsheetML 2003** XML
 *   (`<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" ...>`),
 *   so {@see self::rows()} parses it as XML via `simplexml_load_string()`,
 *   not a spreadsheet library. Confirmed live to carry a leading UTF-8 BOM,
 *   which `simplexml_load_string()` tolerates natively (no manual stripping
 *   needed).
 * - Format: every `<Row>` under `<Worksheet><Table>` has a leading blank
 *   spacer `<Cell/>` followed by up to 3 more cells -- name, an 8-character
 *   "БАЕ код" (BAE code, this source's alpha4+digit4 institution/participant
 *   code), and (for "main"/bold rows only) a BIC. Title/blank/header rows
 *   don't carry a well-formed BAE code, so rather than special-casing "skip
 *   the first N rows" (layouts shift `.xls` exports around year to year),
 *   {@see self::rows()} uses a single robust rule: a `<Row>` is DATA only if
 *   its 3rd cell's text matches `^[A-Za-z]{4}[0-9]{4}$`.
 * - **Bank-level dedup**: a single PSP ("Наименование на ДПУ") appears once
 *   per BAE code it holds -- typically one "main" (bold) row carrying its
 *   BIC, followed by zero or more non-bold "sub"/regional-code rows that
 *   share the SAME 4-letter alpha prefix (e.g. `BPBI9920` "Юробанк България
 *   АД" followed by `BPBI7115`/`BPBI8170`/... for its regional codes) and
 *   carry NO BIC cell at all. Since Bulgarian IBANs carry their bank
 *   identifier as this 4-letter alpha prefix (`bank_code`) and `banks` is
 *   keyed on `(country_code, bank_code, branch_code)` with `branch_code`
 *   always `null` here, importing every BAE code row for the same prefix
 *   would just have the last one silently win the upsert -- so
 *   {@see self::rows()} yields only the FIRST row seen for each 4-letter
 *   prefix (`bank_code`), silently skipping the rest. This also gracefully
 *   handles the rare case of two distinct "main" (bold, BIC-carrying) rows
 *   sharing a prefix (e.g. `Българска народна банка` `BNBG9661` and its
 *   `Българска народна банка СЕБРА плащания` sibling `BNBG5000`, both
 *   confirmed live) -- first-registration wins.
 * - No head-office/branch cell layout mixing: a "sub"/regional row simply has
 *   ONE FEWER `<Cell>` element than a "main" row (no 4th cell at all, not an
 *   empty one), so `bic` naturally comes out `null` for it via
 *   {@see ImporterInterface::rows()}'s documented `bic: string|null,
 *   optional` contract -- moot anyway since such rows are deduped away.
 *
 * CAVEAT: parsing targets the documented/observed source format as of this
 * release -- validate against the live official file before production use;
 * issuing bodies change layouts without notice.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`,
 * `simplexml_load_string()`) to fetch/parse, per {@see ImporterInterface}'s
 * framework-free contract -- even though `src/Import/` itself isn't guarded
 * by `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class BulgarianNationalBankImporter implements ImporterInterface
{
    use NormalizesStrings;

    private const BAE_CODE_PATTERN = '/^[A-Za-z]{4}[0-9]{4}$/';

    public function countryCode(): string
    {
        return 'BG';
    }

    public function sourceId(): string
    {
        return 'bnb';
    }

    public function sourceName(): string
    {
        return 'Bulgarian National Bank';
    }

    public function license(): string
    {
        return 'Bulgarian National Bank';
    }

    public function sourceUrl(): string
    {
        return 'https://www.bnb.bg/RegistersAndServices/RSBAEAndBIC/index.htm?download=MS-Excel';
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

        $workbook = @simplexml_load_string($raw);

        if ($workbook === false) {
            return;
        }

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($workbook->Worksheet as $worksheet) {
            foreach ($worksheet->Table as $table) {
                foreach ($table->Row as $row) {
                    $parsed = self::parseRow($row);

                    if ($parsed === null) {
                        continue; // title/blank/header row: no well-formed BAE code
                    }

                    [$bankCode, $name, $bic] = $parsed;

                    if (isset($seen[$bankCode])) {
                        continue; // same PSP, another BAE code sharing this 4-letter prefix
                    }

                    $seen[$bankCode] = true;

                    yield [
                        'bank_code'   => $bankCode,
                        'branch_code' => null,
                        'name'        => $name,
                        'bic'         => $bic,
                    ];
                }
            }
        }
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string}|null [bankCode, name, bic], or null if this isn't a data row.
     */
    private static function parseRow(SimpleXMLElement $row): ?array
    {
        $cells = $row->Cell;

        if (count($cells) < 3) {
            return null;
        }

        $baeCode = trim((string) $cells[2]->Data);

        if (preg_match(self::BAE_CODE_PATTERN, $baeCode) !== 1) {
            return null;
        }

        $name = self::nullableTrim((string) $cells[1]->Data);
        $bic  = isset($cells[3]) ? self::nullableTrim((string) $cells[3]->Data) : null;

        return [strtoupper(substr($baeCode, 0, 4)), $name, $bic];
    }
}
