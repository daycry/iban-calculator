<?php

declare(strict_types=1);

namespace Daycry\Iban\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;

/**
 * Official-source importer for Azerbaijan (AZ): the Central Bank of
 * Azerbaijan's (CBAR) "bank info" XML feed -- the authoritative list of
 * every settlement participant (banks plus a handful of treasury/government
 * payment participants) backing Azerbaijani IBAN routing.
 *
 * - Live source: {@see self::sourceUrl()}, confirmed live on 2026-07-11.
 * - Format: genuine XML, root `<Data>`, with `<HeadOffices><Operations>`
 *   holding one `<Bank>` element per institution and, separately,
 *   `<BranchOffices><Operations>` holding one `<Branch>` element per
 *   physical branch of those institutions. Relevant `<Bank>` child elements:
 *   `<SWIFTBIC>` (8 characters, e.g. `NABZAZ2C`) and `<Name>`.
 * - **Bank-level filter**: {@see self::rows()} reads ONLY `<HeadOffices>`'s
 *   `<Bank>` elements -- the `<BranchOffices>`'s `<Branch>` elements (over a
 *   thousand of them live, confirmed 2026-07-11) are entirely ignored, same
 *   intent as {@see OenbImporter}'s head-office-only filter, just via a
 *   completely separate branch/head-office section rather than a per-row
 *   marker column.
 * - `bank_code` is the first 4 characters of `<SWIFTBIC>` (this source's
 *   institution/bank identifier, matching the first 4 characters of the
 *   BIC the same institution's Azerbaijani IBANs embed as their bank code,
 *   e.g. `AZ21NABZ00000000137010001944` -> `NABZ`).
 * - **Dedup**: confirmed live, two distinct `<Bank>` entries -- both named
 *   `Dövlət Xəzinədarlığı Agentliyi` (State Treasury Agency), `<Code>`s
 *   `210027` and `210005` -- publish DIFFERENT `<SWIFTBIC>`s
 *   (`CTREAZ24`/`CTREAZ22`) that share the SAME 4-character prefix `CTRE`.
 *   Since `banks` is keyed on `(country_code, bank_code, branch_code)` with
 *   `branch_code` always `null` here, importing both would just have the
 *   second one silently win the upsert -- {@see self::rows()} therefore
 *   yields only the FIRST `<Bank>` seen for each 4-character `bank_code`
 *   prefix, silently skipping the rest.
 * - Encoding: confirmed live to be genuine UTF-8, no BOM.
 * - **License**: CBAR does not publish an explicit reuse/redistribution
 *   license for this feed -- {@see self::license()} therefore records the
 *   issuing body's name itself (the same "cite the source, no other terms
 *   confirmed" convention this package uses when a source's licensing is
 *   unclear, e.g. {@see NationalBankOfSlovakiaImporter}). No data from this
 *   source is bundled with this package; consumers who want it run
 *   `iban:update --source=cbar` themselves against the live URL above.
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
final class CentralBankOfAzerbaijanImporter implements ImporterInterface
{
    public function countryCode(): string
    {
        return 'AZ';
    }

    public function sourceId(): string
    {
        return 'cbar';
    }

    public function sourceName(): string
    {
        return 'Central Bank of Azerbaijan';
    }

    public function license(): string
    {
        return 'Central Bank of Azerbaijan';
    }

    public function sourceUrl(): string
    {
        return 'https://www.cbar.az/bankinfonew/banks.xml';
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

        $data = @simplexml_load_string($raw);

        if ($data === false) {
            return;
        }

        /** @var array<string, true> $seen */
        $seen = [];

        foreach ($data->HeadOffices as $headOffices) {
            foreach ($headOffices->Operations as $operations) {
                foreach ($operations->Bank as $bank) {
                    $swiftBic = strtoupper(trim((string) $bank->SWIFTBIC));

                    if (strlen($swiftBic) < 4) {
                        continue; // malformed/unexpectedly short SWIFTBIC
                    }

                    $bankCode = substr($swiftBic, 0, 4);

                    if (isset($seen[$bankCode])) {
                        continue; // another institution/settlement account sharing this 4-char prefix
                    }

                    $seen[$bankCode] = true;

                    yield [
                        'bank_code'   => $bankCode,
                        'branch_code' => null,
                        'name'        => self::nullableTrim((string) $bank->Name),
                        'bic'         => $swiftBic,
                    ];
                }
            }
        }
    }

    private static function nullableTrim(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
