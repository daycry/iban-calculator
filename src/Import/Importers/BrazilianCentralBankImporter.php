<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Brazil (BR): Banco Central do Brasil's STR
 * (Sistema de Transferência de Reservas) participant list, the authoritative
 * directory of ISPB codes (this source's `bank_code` -- the 8-digit segment
 * a Brazilian IBAN's BBAN starts with) identifying every institution
 * participating in Brazil's real-time settlement system.
 *
 * - Live source: {@see self::sourceUrl()}, confirmed live on 2026-07-11.
 * - Format: `,`-delimited (COMMA) CSV, UTF-8 WITH a leading BOM (`EF BB BF`),
 *   LF line endings, 7 columns, some fields quoted (a handful of names
 *   contain commas, e.g. `"SANTINVEST S.A. - CREDITO, FINANCIAMENTO E
 *   INVESTIMENTOS"`) -- parsed with `fgetcsv()`, never a naive
 *   `explode()`/split.
 * - Columns are matched by FIXED POSITION (0-based) rather than by header
 *   name: the real header row is
 *   `ISPB,Nome_Reduzido,Número_Código,Participa_da_Compe,Acesso_Principal,
 *   Nome_Extenso,Início_da_Operação` -- `0`=ISPB (`bank_code`),
 *   `1`=Nome_Reduzido (short name, used as a `name` fallback), `5`=Nome_Extenso
 *   (full legal name, preferred `name`). `Número_Código`/`Início_da_Operação`
 *   carry accented header labels, which is a further reason to match by
 *   position rather than by header text.
 * - `bank_code` is the ISPB, always published already zero-padded to 8
 *   digits (e.g. `00360305`) -- kept as a STRING (never cast to `int`) and
 *   defensively left-padded to 8 digits with `str_pad()` in case a future
 *   export ever drops a leading zero. ISPB is unique per row -- no
 *   deduplication is needed (unlike OeNB/Bundesbank's head-office/branch
 *   dedup).
 * - `name` prefers `Nome_Extenso` (the full legal name); when a row's
 *   `Nome_Extenso` is blank, `Nome_Reduzido` (the short name) is used
 *   instead, per {@see ImporterInterface::rows()}'s documented `name`
 *   contract.
 * - `branch_code` is always `null`: this source lists ISPBs (bank-level),
 *   not the 5-digit branch ("agência") a Brazilian IBAN also carries --
 *   {@see \Daycry\Iban\Resolver\Resolver}'s `findByBankCode(cc, bank, null)`
 *   fallback resolves any branch of a bank-level-only seeded row.
 * - Encoding: the source is UTF-8 WITH a BOM (`Início_da_Operação`,
 *   `Número_Código`, ... are already correctly-encoded multibyte UTF-8) --
 *   {@see self::rows()} strips the leading BOM defensively. It would in any
 *   case only occupy the header row's first cell, which {@see self::rows()}
 *   discards by position, so it cannot reach a yielded field either way; it
 *   must NOT Latin-1-decode this source.
 *
 * CAVEAT: parsing targets the documented/observed STR-participant CSV layout
 * as of this release -- validate against the live official file before
 * production use; Banco Central do Brasil could change the layout without
 * notice.
 *
 * LICENSING: Banco Central do Brasil publishes this dataset as open data
 * under the Open Data Commons Open Database License (ODbL).
 *
 * FRAMEWORK-FREE: uses only native PHP (`file_get_contents()`, `fgetcsv()`)
 * to fetch/parse, per {@see ImporterInterface}'s framework-free contract --
 * even though `src/Import/` itself isn't guarded by
 * `tests/Architecture/CoreIsFrameworkFreeTest.php`.
 *
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class BrazilianCentralBankImporter implements ImporterInterface
{
    private const INDEX_ISPB          = 0;
    private const INDEX_NOME_REDUZIDO = 1;
    private const INDEX_NOME_EXTENSO  = 5;

    private const MINIMUM_FIELD_COUNT = self::INDEX_NOME_EXTENSO + 1;

    private const BANK_CODE_LENGTH = 8;

    public function countryCode(): string
    {
        return 'BR';
    }

    public function sourceId(): string
    {
        return 'bcb';
    }

    public function sourceName(): string
    {
        return 'Banco Central do Brasil';
    }

    public function license(): string
    {
        return 'Banco Central do Brasil (ODbL)';
    }

    public function sourceUrl(): string
    {
        return 'https://www.bcb.gov.br/content/estabilidadefinanceira/str1/ParticipantesSTR.csv';
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

            if (count($fields) < self::MINIMUM_FIELD_COUNT) {
                continue; // malformed row: too short to carry Nome_Extenso
            }

            $ispb = trim($fields[self::INDEX_ISPB] ?? '');

            if ($ispb === '') {
                continue;
            }

            $nomeExtenso  = self::nullableTrim($fields[self::INDEX_NOME_EXTENSO] ?? '');
            $nomeReduzido = self::nullableTrim($fields[self::INDEX_NOME_REDUZIDO] ?? '');

            yield [
                'bank_code'   => str_pad($ispb, self::BANK_CODE_LENGTH, '0', STR_PAD_LEFT),
                'branch_code' => null,
                'name'        => $nomeExtenso ?? $nomeReduzido,
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
     * No Latin-1 fallback: this source is confirmed UTF-8.
     */
    private static function stripBom(string $raw): string
    {
        return str_starts_with($raw, "\xEF\xBB\xBF") ? substr($raw, 3) : $raw;
    }
}
