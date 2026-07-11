<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\Concerns\NormalizesStrings;

/**
 * Official-source importer for Moldova (MD): the National Bank of Moldova's
 * (Banca Naţională a Moldovei, BNM) "licensed banks" XML feed -- the
 * authoritative list of every SWIFT-connected participant of Moldova's
 * payment system, including the explicit 2-character `IBANIdentifier` this
 * source publishes as each bank's Moldovan IBAN routing code.
 *
 * - Live source: {@see self::sourceUrl()}, confirmed live on 2026-07-11.
 * - Format: genuine XML, root `<Update><Participants><Participant .../>...`,
 *   one self-closing (or empty-bodied) `<Participant>` element per
 *   institution/sub-account, attributes only -- no element children carry
 *   the fields this importer needs. Relevant attributes: `BIC`, `Name`, and
 *   `IBANIdentifier` (2 characters for a genuine bank-level participant,
 *   empty `""` for the many internal sub-account/branch rows the same
 *   institution also publishes, e.g. `B.C.'VICTORIABANK'S.A. suc.nr.24
 *   Ialoveni`'s `IBANIdentifier=""`). Confirmed live: the XML prolog
 *   declares `encoding="latin1"` -- `simplexml_load_string()` (backed by
 *   libxml) decodes that natively into UTF-8-valued strings, so no manual
 *   iconv/mbstring conversion is needed here (unlike some of this package's
 *   CSV importers, which must decode Windows-125x/ISO-8859-x bytes by hand).
 * - **Bank-level filter**: only `<Participant>` elements with a non-empty
 *   `IBANIdentifier` are genuine bank-level routing entries -- the same
 *   pattern as {@see OenbImporter}'s head-office-only filter, just keyed on
 *   a different attribute. {@see self::rows()} therefore skips (does not
 *   yield) any `Participant` whose `IBANIdentifier` is missing or `""`.
 *   Confirmed live: every non-empty `IBANIdentifier` in this source is
 *   already unique -- no duplicate-prefix collision like
 *   {@see BulgarianNationalBankImporter}'s or
 *   {@see CentralBankOfAzerbaijanImporter}'s sources -- but {@see self::rows()}
 *   still guards defensively against a same-identifier repeat (first
 *   occurrence wins), matching this package's other bank-level importers.
 * - Moldovan IBANs carry no branch segment, so `branch_code` stays `null`
 *   here, same as every other importer in this package.
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
final class NationalBankOfMoldovaImporter implements ImporterInterface
{
    use NormalizesStrings;

    public function countryCode(): string
    {
        return 'MD';
    }

    public function sourceId(): string
    {
        return 'bnm';
    }

    public function sourceName(): string
    {
        return 'National Bank of Moldova';
    }

    public function license(): string
    {
        return 'National Bank of Moldova';
    }

    public function sourceUrl(): string
    {
        return 'https://www.bnm.md/en/licensed_banks_xml';
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

        $update = @simplexml_load_string($raw);

        if ($update === false) {
            return;
        }

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($update->Participants as $participants) {
            foreach ($participants->Participant as $participant) {
                $bankCode = strtoupper(trim((string) $participant['IBANIdentifier']));

                if ($bankCode === '') {
                    continue; // internal sub-account/branch row: no bank-level IBAN identifier
                }

                if (isset($seen[$bankCode])) {
                    continue; // defensive: this source hasn't published a duplicate live, but guard anyway
                }

                $seen[$bankCode] = true;

                yield [
                    'bank_code'   => $bankCode,
                    'branch_code' => null,
                    'name'        => self::nullableTrim((string) $participant['Name']),
                    'bic'         => self::nullableTrim((string) $participant['BIC']),
                ];
            }
        }
    }
}
