<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers\Concerns;

/**
 * Shared fetch -> decode -> parse scaffold for every importer whose source is
 * a delimited CSV, reused by nine importers
 * ({@see \Daycry\Iban\Import\Importers\OenbImporter} (AT),
 * {@see \Daycry\Iban\Import\Importers\BancoDeEspanaImporter} (ES),
 * {@see \Daycry\Iban\Import\Importers\BrazilianCentralBankImporter} (BR),
 * {@see \Daycry\Iban\Import\Importers\CzechNationalBankImporter} (CZ),
 * {@see \Daycry\Iban\Import\Importers\BetaalverenigingImporter} (NL),
 * {@see \Daycry\Iban\Import\Importers\EpcRegisterImporter} (GB/GI/IE/LV/RO),
 * {@see \Daycry\Iban\Import\Importers\HellenicBankAssociationImporter} (GR),
 * {@see \Daycry\Iban\Import\Importers\BankOfSloveniaImporter} (SI),
 * {@see \Daycry\Iban\Import\Importers\NationalBankOfSlovakiaImporter} (SK))
 * that would otherwise each copy-paste the same
 * `file_get_contents() -> decode -> php://temp -> fgetcsv()` loop, mirroring
 * how {@see ParsesSixBankMaster} centralizes the SIX Bank Master V3 CSV
 * lifecycle and {@see ReadsXlsxSource} the `.xlsx` lifecycle.
 *
 * Only the fetch/decode/parse plumbing is centralized here -- each importer
 * keeps its own header handling (matched by column NAME, discarded
 * unconditionally as a positional column layout, or absent entirely with
 * every row self-filtered by a `bank_code` pattern), column mapping, dedup
 * and business-logic filters exactly as before.
 *
 * {@see self::decodeCsvBytes()} is a template-method hook: the default here
 * strips a leading UTF-8 BOM and, if the remaining bytes aren't valid UTF-8,
 * falls back to a Windows-1252 conversion -- {@see OenbImporter}'s original,
 * historical defensive posture (Austrian government CSV exports have shipped
 * in either encoding). For every OTHER importer that keeps this default
 * unmodified, that fallback branch is a no-op in practice: their sources are
 * independently confirmed genuine UTF-8, so the "isn't valid UTF-8" condition
 * never triggers for them -- the default only ever changes behavior for bytes
 * that weren't valid UTF-8 to begin with, which none of those sources ship.
 * Importers whose source is instead a FIXED legacy codepage -- Greece
 * (Windows-1253), Slovenia/Slovakia (Windows-1250) -- override
 * {@see self::decodeCsvBytes()} to `iconv()` from that specific codepage,
 * since the default's Windows-1252 guess would mis-decode their genuinely
 * non-UTF-8 bytes.
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fopen()`,
 * `fgetcsv()`, `mb_check_encoding()`, `mb_convert_encoding()`), per
 * {@see \Daycry\Iban\Contracts\ImporterInterface}'s framework-free contract
 * -- even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 */
trait ReadsCsvSource
{
    use NormalizesStrings;

    /**
     * Fetches (from `$localFile` if given, else `$sourceUrl`), decodes via
     * {@see self::decodeCsvBytes()}, and yields each parsed record as a
     * `fgetcsv()`-shaped row using `$delimiter`. Yields nothing at all on any
     * fetch failure.
     *
     * @return iterable<int, array<int, string|null>>
     */
    protected function csvRecords(?string $localFile, string $sourceUrl, string $delimiter): iterable
    {
        $raw = $localFile !== null
            ? @file_get_contents($localFile)
            : @file_get_contents($sourceUrl);

        if ($raw === false || $raw === '') {
            return;
        }

        yield from $this->parseCsvBytes($raw, $delimiter);
    }

    /**
     * Decodes already-fetched `$raw` bytes via {@see self::decodeCsvBytes()}
     * and yields each parsed record as a `fgetcsv()`-shaped row using
     * `$delimiter`. Split out from {@see self::csvRecords()} for importers
     * (e.g. {@see \Daycry\Iban\Import\Importers\EpcRegisterImporter}) that
     * fetch their own raw bytes from more than one source URL and parse each
     * one the same way. Yields nothing at all on a `php://temp` stream
     * failure.
     *
     * @return iterable<int, array<int, string|null>>
     */
    protected function parseCsvBytes(string $raw, string $delimiter): iterable
    {
        $raw = $this->decodeCsvBytes($raw);

        $stream = fopen('php://temp', 'r+b');

        if ($stream === false) {
            return;
        }

        fwrite($stream, $raw);
        rewind($stream);

        while (($fields = fgetcsv($stream, 0, $delimiter)) !== false) {
            yield $fields;
        }

        fclose($stream);
    }

    /**
     * Decodes raw source bytes to UTF-8: strips a leading UTF-8 BOM, then
     * falls back to a Windows-1252 -> UTF-8 conversion if the remaining bytes
     * aren't already valid UTF-8. Importers whose source is a FIXED legacy
     * codepage override this (see the class docblock).
     */
    protected function decodeCsvBytes(string $raw): string
    {
        $raw = self::stripBom($raw);

        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
        }

        return $raw;
    }
}
