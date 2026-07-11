<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Poland (PL): Narodowy Bank Polski's EWIB
 * ("Elektroniczny Wykaz Identyfikatorów Bankowych", electronic register of
 * bank identifiers) XML feed -- the authoritative list of every 8-digit
 * "numer rozliczeniowy" (settlement/clearing code) backing Polish
 * IBAN/ELIXIR routing.
 *
 * - Live source: {@see self::sourceUrl()}, confirmed live on 2026-07-11.
 * - Format: genuine XML, root `<Instytucje>`, one `<Instytucja>`
 *   (institution) element per bank, each carrying `<NazwaInstytucji>` (the
 *   bank's name) and one-or-more nested `<Jednostka>` (organizational unit --
 *   head office, department, branch, ...) elements, each of which in turn
 *   carries one-or-more `<NumerRozliczeniowy>` elements holding the actual
 *   8-digit `<NrRozliczeniowy>` settlement code. This nesting is PER
 *   ORGANIZATIONAL UNIT, i.e. per branch, not per bank -- a single
 *   `<Instytucja>` (confirmed live, e.g. NBP itself and "Erste Bank Polska
 *   Spółka Akcyjna") routinely publishes dozens of `<Jednostka>`/
 *   `<NumerRozliczeniowy>` combinations.
 * - **Bank-level rollup**: Polish IBANs carry only the FIRST 3 digits of the
 *   8-digit settlement code as their bank identifier (`bank_code`) --
 *   the remaining 5 digits distinguish the specific branch/unit, which
 *   `banks` has no column for here (`branch_code` stays `null`, same as
 *   every other importer in this package). {@see self::rows()} therefore
 *   walks every `<NumerRozliczeniowy>`/`<NrRozliczeniowy>` nested ANYWHERE
 *   under an `<Instytucja>` (via a relative XPath query, since the nesting
 *   depth/order of `<Jednostka>` varies), truncates each code's first 3
 *   digits, and yields exactly ONE row per unique 3-digit `bank_code` --
 *   first occurrence wins, all the rest (same bank, another branch/unit)
 *   are silently skipped. This keeps the imported table bank-level and
 *   small instead of one row per branch; the specific branch/unit name
 *   backing the retained settlement code is NOT preserved, only the parent
 *   `<Instytucja>`'s `<NazwaInstytucji>`.
 * - Confirmed live: `<NrInstytucji>` (the institution's own registration
 *   number) usually already equals this 3-digit `bank_code` prefix, but
 *   {@see self::rows()} deliberately derives `bank_code` from the settlement
 *   code itself (per this source's own IBAN-routing semantics) rather than
 *   trusting `<NrInstytucji>` to always be exactly 3 digits.
 * - Encoding: confirmed live to be genuine UTF-8, no BOM.
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
final class NationalBankOfPolandImporter implements ImporterInterface
{
    public function countryCode(): string
    {
        return 'PL';
    }

    public function sourceId(): string
    {
        return 'nbp';
    }

    public function sourceName(): string
    {
        return 'Narodowy Bank Polski (EWIB)';
    }

    public function license(): string
    {
        return 'Narodowy Bank Polski (public sector information, free reuse)';
    }

    public function sourceUrl(): string
    {
        return 'https://ewib.nbp.pl/plewiba?dokNazwa=plewiba.xml';
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

        $instytucje = @simplexml_load_string($raw);

        if ($instytucje === false) {
            return;
        }

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($instytucje->Instytucja as $instytucja) {
            $name = self::nullableTrim((string) $instytucja->NazwaInstytucji);

            /** @var list<\SimpleXMLElement>|false $codes */
            $codes = $instytucja->xpath('.//NumerRozliczeniowy/NrRozliczeniowy');

            if ($codes === false) {
                continue;
            }

            foreach ($codes as $codeElement) {
                $fullCode = trim((string) $codeElement);

                if (strlen($fullCode) < 3) {
                    continue; // malformed/unexpectedly short settlement code
                }

                $bankCode = substr($fullCode, 0, 3);

                if (isset($seen[$bankCode])) {
                    continue; // same bank, another branch/unit settlement code
                }

                $seen[$bankCode] = true;

                yield [
                    'bank_code'   => $bankCode,
                    'branch_code' => null,
                    'name'        => $name,
                ];
            }
        }
    }

    private static function nullableTrim(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
