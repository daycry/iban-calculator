<?php

declare(strict_types=1);

namespace Tests\Import\Importers;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\Importers\EpcRegisterImporter;
use PHPUnit\Framework\TestCase;

/**
 * Exercises {@see EpcRegisterImporter} in isolation (plain PHPUnit,
 * framework-free) against the hand-crafted fixture
 * `tests/Fixtures/import/epc_sct_sample.csv` -- a representative,
 * structurally faithful sample of the EPC's real, live `sct.csv` SEPA
 * Register export (comma-delimited, all fields quoted, no BOM, `Country`
 * column carrying a full ALL-CAPS English name -- confirmed live 2026-07-11).
 *
 * Unlike every other bundled importer, this one is instantiated once per
 * target country (GB, GI, IE, LV, RO), so most assertions here are repeated
 * per-country rather than against a single fixed instance.
 *
 * @see \Daycry\Iban\Import\Importers\EpcRegisterImporter
 * @see \Daycry\Iban\Import\ImporterRegistry::registerDefaults()
 */
final class EpcRegisterImporterTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../../Fixtures/import/epc_sct_sample.csv';

    public function testImplementsImporterInterface(): void
    {
        self::assertInstanceOf(ImporterInterface::class, new EpcRegisterImporter('GB'));
    }

    public function testCountryCodeReflectsTheConstructorArgumentUppercased(): void
    {
        self::assertSame('GB', (new EpcRegisterImporter('GB'))->countryCode());
        self::assertSame('GB', (new EpcRegisterImporter('gb'))->countryCode());
        self::assertSame('IE', (new EpcRegisterImporter('ie'))->countryCode());
        self::assertSame('RO', (new EpcRegisterImporter('Ro'))->countryCode());
        self::assertSame('LV', (new EpcRegisterImporter('lv'))->countryCode());
        self::assertSame('GI', (new EpcRegisterImporter('gi'))->countryCode());
    }

    public function testMetadataIsAsDocumented(): void
    {
        $importer = new EpcRegisterImporter('GB');

        self::assertSame('epc', $importer->sourceId());
        self::assertSame('European Payments Council (SEPA Register)', $importer->sourceName());
        self::assertSame('EPC SEPA Register (credit EPC, no resale as-is)', $importer->license());
        self::assertSame(
            'https://www.europeanpaymentscouncil.eu/sites/default/files/participants_export/sct/sct.csv',
            $importer->sourceUrl(),
        );
    }

    public function testLicenseFitsTheSourceLicenseColumnWidth(): void
    {
        // `banks.source_license` is VARCHAR(64) -- a longer string would be
        // silently truncated by the database (see ImportRunner/CreateBanksTable).
        self::assertLessThanOrEqual(64, strlen((new EpcRegisterImporter('GB'))->license()));
    }

    public function testRowsYieldsOnlyGbBanksDedupedAndSkippingInvalidOrStaleRows(): void
    {
        $rows = iterator_to_array((new EpcRegisterImporter('GB'))->rows(self::FIXTURE), false);

        // 6 GB rows in the fixture: LOYDGB2LXXX + LOYDJES1XXX (dedup to 1),
        // BARCGB22XXX, SRLGGB3LXXX, a past-leaving-date row (skipped), and
        // an empty-BIC row (skipped) -- 3 unique bank_code rows survive.
        self::assertCount(3, $rows);

        $bankCodes = array_column($rows, 'bank_code');
        self::assertSame(['LOYD', 'BARC', 'SRLG'], $bankCodes);

        $lloyds = $rows[0];
        self::assertSame('LOYD', $lloyds['bank_code']);
        self::assertNull($lloyds['branch_code']);
        // First occurrence ("Lloyds Bank plc") wins over the deduped
        // "Lloyds Bank Corporate Markets Plc" sibling sharing the LOYD prefix.
        self::assertSame('Lloyds Bank plc', $lloyds['name']);
        self::assertSame('LOYDGB2LXXX', $lloyds['bic']);
        self::assertSame('LONDON', $lloyds['city']);
        self::assertTrue($lloyds['sepa_sct']);

        $barclays = $rows[1];
        self::assertSame('BARC', $barclays['bank_code']);
        self::assertSame('BARCLAYS BANK PLC', $barclays['name']);
        self::assertSame('BARCGB22XXX', $barclays['bic']);
        self::assertSame('1 Churchill Place, Canary Wharf', $barclays['address']); // embedded comma preserved
        self::assertTrue($barclays['sepa_sct']);

        $starling = $rows[2];
        self::assertSame('SRLG', $starling['bank_code']);
        self::assertSame('Starling Bank Limited', $starling['name']);
        self::assertSame('SRLGGB3LXXX', $starling['bic']);
        self::assertTrue($starling['sepa_sct']);
    }

    public function testRowsExcludesTheDedupedLloydsSiblingAndTheInvalidRows(): void
    {
        $rows      = iterator_to_array((new EpcRegisterImporter('GB'))->rows(self::FIXTURE), false);
        $bankCodes = array_column($rows, 'bank_code');

        // "Lloyds Bank Corporate Markets Plc" (LOYDJES1XXX) shares the LOYD
        // prefix and must not have produced its own row -- only 1 row bears
        // the 'LOYD' bank_code, not 2.
        self::assertNotContains('DEPT', $bankCodes, 'The past-leaving-date row must have been skipped.');
        self::assertSame(1, count(array_filter($bankCodes, static fn (string $c): bool => $c === 'LOYD')));

        // The FRANCE row must never surface under a GB-scoped import.
        self::assertNotContains('BNPA', $bankCodes);
    }

    public function testRowsExcludesNonTargetCountryRows(): void
    {
        foreach (['GB', 'IE', 'RO', 'LV', 'GI'] as $country) {
            $rows      = iterator_to_array((new EpcRegisterImporter($country))->rows(self::FIXTURE), false);
            $bankCodes = array_column($rows, 'bank_code');

            self::assertNotContains('BNPA', $bankCodes, "FR row must not surface for {$country}.");
        }
    }

    public function testRowsForIrelandYieldsAibkAndBofi(): void
    {
        $rows = iterator_to_array((new EpcRegisterImporter('IE'))->rows(self::FIXTURE), false);

        self::assertCount(2, $rows);
        self::assertSame(['AIBK', 'BOFI'], array_column($rows, 'bank_code'));

        self::assertSame('Allied Irish Banks plc', $rows[0]['name']);
        self::assertSame('AIBKIE2DXXX', $rows[0]['bic']);
        self::assertSame('The Governor and Company of the Bank of Ireland', $rows[1]['name']);
        self::assertSame('BOFIIE2DXXX', $rows[1]['bic']);
    }

    public function testRowsForRomaniaYieldsBtrl(): void
    {
        $rows = iterator_to_array((new EpcRegisterImporter('RO'))->rows(self::FIXTURE), false);

        self::assertCount(1, $rows);
        self::assertSame('BTRL', $rows[0]['bank_code']);
        self::assertSame('Banca Transilvania S.A.', $rows[0]['name']);
        self::assertSame('BTRLRO22XXX', $rows[0]['bic']);
        self::assertNull($rows[0]['branch_code']);
    }

    public function testRowsForLatviaYieldsHaba(): void
    {
        $rows = iterator_to_array((new EpcRegisterImporter('LV'))->rows(self::FIXTURE), false);

        self::assertCount(1, $rows);
        self::assertSame('HABA', $rows[0]['bank_code']);
        self::assertSame('AS Swedbank', $rows[0]['name']);
        self::assertSame('HABALV22XXX', $rows[0]['bic']);
    }

    public function testRowsForGibraltarYieldsXapo(): void
    {
        $rows = iterator_to_array((new EpcRegisterImporter('GI'))->rows(self::FIXTURE), false);

        self::assertCount(1, $rows);
        self::assertSame('XAPO', $rows[0]['bank_code']);
        self::assertSame('XAPO Bank Limited', $rows[0]['name']);
        self::assertSame('XAPOGIGIXXX', $rows[0]['bic']);
    }

    public function testRowsReturnsEmptyIterableWhenTheFileCannotBeRead(): void
    {
        $rows = iterator_to_array((new EpcRegisterImporter('GB'))->rows('/nonexistent/path/epc.csv'), false);

        self::assertSame([], $rows);
    }
}
