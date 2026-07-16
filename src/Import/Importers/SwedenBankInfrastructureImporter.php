<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\NormalizesStrings;
use Daycry\Iban\Import\Importers\Concerns\ReadsCsvSource;

/**
 * Official-source importer for Sweden (SE): the community-maintained
 * `Bankinfrastruktur/BankData` mirror's `source.psv`, the machine-readable
 * pipe-separated directory that maps every Swedish clearing-number range to
 * its bank-level "IBAN id" (this source's `bank_code`), BIC and bank name.
 *
 * Sweden's authoritative publisher (Bankgirot / BSAB, bankinfrastruktur.se)
 * ships the same information only as PDF/DOCX with no open license, which
 * would be tier D on its own -- the MIT-licensed community mirror is what
 * makes SE constructible here (see the initiative's `research.md` §SE).
 *
 * - Live source: {@see self::sourceUrl()} -- the raw `Data/source.psv` on
 *   `raw.githubusercontent.com`, fetchable without authentication. If GitHub
 *   ever blocks that fetch, the same file can be handed in via
 *   `iban:update --file=...` (the tested/supported path here).
 * - Format: pipe (`|`) separated, UTF-8. Header (confirmed against the live
 *   mirror): `#ClrStart|ClrEnd|IbanId|BIC|BankName|...`. Columns are matched
 *   by NAME via the header row, not position.
 * - `bank_code` = the `IbanId` column, the 3-digit bank identifier a Swedish
 *   IBAN embeds at positions 5-7 (e.g. `SE45 5000 ...` -> `500` = SEB). The
 *   **`ClrStart`/`ClrEnd` clearing-number columns (4-5 digits) are NOT the
 *   IBAN bank code and are deliberately ignored** -- using them would fail on
 *   both length and semantics.
 * - **Dedup by `IbanId`**: a single bank owns several clearing ranges, so the
 *   same `IbanId` recurs across many rows (Nordea = `300` appears repeatedly)
 *   -- {@see self::rows()} yields only the FIRST row seen for each distinct
 *   `IbanId`, same first-occurrence-wins rule the other bank-level importers
 *   use.
 * - A row whose `IbanId` is blank or not exactly three digits (a
 *   clearing-only range with no bank-level IBAN id) is skipped -- there is no
 *   IBAN bank code to key a `banks` row on.
 *
 * LICENSING: the mirror is published under the MIT license, which permits
 * fetching, redistribution and commercial use. The bundled BICs originate
 * from that same MIT dataset.
 *
 * CAVEAT: parsing targets the documented/observed `source.psv` layout as of
 * this release -- validate against the live file before production use; the
 * mirror could change the column set without notice.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`
 * via {@see ReadsCsvSource}) to fetch/parse, per {@see ImporterInterface}'s
 * framework-free contract -- even though `src/Import/` itself isn't guarded
 * by `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 */
final class SwedenBankInfrastructureImporter implements ImporterInterface
{
    use ReadsCsvSource;
    use NormalizesStrings;

    private const COLUMN_IBAN_ID = 'IbanId';
    private const COLUMN_BIC     = 'BIC';
    private const COLUMN_NAME    = 'BankName';

    private const IBAN_ID_PATTERN = '/^[0-9]{3}$/';

    public function countryCode(): string
    {
        return 'SE';
    }

    public function sourceId(): string
    {
        return 'bankinfrastruktur';
    }

    public function sourceName(): string
    {
        return 'Bankinfrastruktur BankData';
    }

    public function license(): string
    {
        return 'MIT (Bankinfrastruktur BankData)';
    }

    public function sourceUrl(): string
    {
        return 'https://raw.githubusercontent.com/Bankinfrastruktur/BankData/main/Data/source.psv';
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function rows(?string $localFile = null): iterable
    {
        /** @var array<string, int>|null $columns */
        $columns = null;

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($this->csvRecords($localFile, $this->sourceUrl(), '|') as $fields) {
            if ($fields === [null]) {
                continue; // blank line
            }

            /** @var list<string> $fields */
            $fields = array_map(static fn (?string $value): string => $value ?? '', $fields);

            if ($columns === null) {
                $columns = self::mapColumns($fields);

                if ($columns === null) {
                    return; // header not recognized -> nothing to import
                }

                continue;
            }

            $ibanId = trim($fields[$columns[self::COLUMN_IBAN_ID]] ?? '');

            if (preg_match(self::IBAN_ID_PATTERN, $ibanId) !== 1) {
                continue; // clearing-only range with no bank-level IBAN id
            }

            if (isset($seen[$ibanId])) {
                continue; // another clearing range of the same bank
            }

            $seen[$ibanId] = true;

            yield [
                'bank_code'   => $ibanId,
                'branch_code' => null,
                'name'        => self::nullableTrim($fields[$columns[self::COLUMN_NAME]] ?? ''),
                'bic'         => self::nullableTrim($fields[$columns[self::COLUMN_BIC]] ?? ''),
            ];
        }
    }

    /**
     * Maps the header row's cells to the column indices this importer needs,
     * or `null` if any required column is absent.
     *
     * @param list<string> $header
     *
     * @return array<string, int>|null
     */
    private static function mapColumns(array $header): ?array
    {
        $wanted  = [self::COLUMN_IBAN_ID, self::COLUMN_BIC, self::COLUMN_NAME];
        $columns = [];

        foreach ($header as $index => $cell) {
            $trimmed = trim($cell);

            if (in_array($trimmed, $wanted, true) && ! isset($columns[$trimmed])) {
                $columns[$trimmed] = $index;
            }
        }

        return count($columns) === count($wanted) ? $columns : null;
    }
}
