<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers\Concerns;

use Daycry\Iban\Import\Support\XlsxReader;
use RuntimeException;

/**
 * Shared download-to-temp-file-then-parse flow for every importer whose
 * source is a genuine `.xlsx` (OOXML) spreadsheet, read via
 * {@see XlsxReader} -- reused by seven importers
 * ({@see \Daycry\Iban\Import\Importers\BitsNorwayImporter} (NO),
 * {@see \Daycry\Iban\Import\Importers\CentralBankOfMaltaImporter} (MT),
 * {@see \Daycry\Iban\Import\Importers\CroatianNationalBankImporter} (HR),
 * {@see \Daycry\Iban\Import\Importers\LuxembourgBankersAssociationImporter} (LU),
 * {@see \Daycry\Iban\Import\Importers\MagyarNemzetiBankImporter} (HU),
 * {@see \Daycry\Iban\Import\Importers\NationalBankOfBelgiumImporter} (BE),
 * {@see \Daycry\Iban\Import\Importers\NationalBankOfGeorgiaImporter} (GE))
 * that would otherwise copy-paste the exact same fetch/tempnam/parse/unlink
 * lifecycle, mirroring how {@see ParsesSixBankMaster} centralizes the
 * SIX Bank Master V3 CSV lifecycle shared by
 * {@see \Daycry\Iban\Import\Importers\SixImporter}/
 * {@see \Daycry\Iban\Import\Importers\LiechtensteinImporter}.
 *
 * Centralizing this also closes a latent leak the copy-pasted version had:
 * each importer's own `@unlink($tmp)` sat as plain code AFTER its
 * column-mapping loop (not in a `finally`), so an abandoned generator (a
 * caller that stops iterating `rows()` partway through, e.g. `break`s out of
 * a `foreach` early) would never reach that `unlink()` call and leave the
 * downloaded temp file behind. {@see self::readXlsxGrid()} sidesteps this
 * entirely: it is a plain method, not itself a generator, so its `finally`
 * always runs to completion -- and the downloaded temp file is always
 * cleaned up -- before the calling importer's `rows()` generator starts
 * yielding anything, regardless of whether the caller consumes every row.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `tempnam()`)
 * plus {@see XlsxReader} (itself framework-free), per
 * {@see \Daycry\Iban\Contracts\ImporterInterface}'s framework-free contract
 * -- even though `src/Import/` itself isn't covered by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 */
trait ReadsXlsxSource
{
    /**
     * Reads the first worksheet of an `.xlsx` source as a plain grid: uses
     * `$localFile` directly if given, otherwise fetches `$sourceUrl` live
     * into a temp file. Returns `[]` on any fetch failure, temp-file
     * creation failure, or {@see XlsxReader::readFirstSheet()} parse
     * failure -- never throws. Any temp file this method creates itself is
     * always removed before it returns, whether the read succeeded or not.
     *
     * @return list<list<string>> the first worksheet grid, or `[]` on any
     *   fetch/parse failure
     */
    protected function readXlsxGrid(?string $localFile, string $sourceUrl): array
    {
        $tmp = null;

        try {
            if ($localFile !== null) {
                $path = $localFile;
            } else {
                $raw = @file_get_contents($sourceUrl);

                if ($raw === false || $raw === '') {
                    return [];
                }

                $candidate = tempnam(sys_get_temp_dir(), 'iban_xlsx_');

                if ($candidate === false) {
                    return [];
                }

                $tmp = $candidate;
                file_put_contents($tmp, $raw);
                $path = $tmp;
            }

            try {
                return XlsxReader::readFirstSheet($path);
            } catch (RuntimeException) {
                return [];
            }
        } finally {
            if ($tmp !== null) {
                @unlink($tmp);
            }
        }
    }
}
