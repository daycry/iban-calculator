<?php

declare(strict_types=1);

namespace Tests\Import;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\ImporterRegistry;
use Daycry\Iban\Import\Importers\AndorranBankingImporter;
use Daycry\Iban\Import\Importers\BancoDeEspanaImporter;
use Daycry\Iban\Import\Importers\BancoDePortugalImporter;
use Daycry\Iban\Import\Importers\BankOfIsraelImporter;
use Daycry\Iban\Import\Importers\BankOfSloveniaImporter;
use Daycry\Iban\Import\Importers\BetaalverenigingImporter;
use Daycry\Iban\Import\Importers\BitsNorwayImporter;
use Daycry\Iban\Import\Importers\BrazilianCentralBankImporter;
use Daycry\Iban\Import\Importers\BulgarianNationalBankImporter;
use Daycry\Iban\Import\Importers\BundesbankImporter;
use Daycry\Iban\Import\Importers\CentralBankOfAzerbaijanImporter;
use Daycry\Iban\Import\Importers\CentralBankOfCyprusImporter;
use Daycry\Iban\Import\Importers\CentralBankOfMaltaImporter;
use Daycry\Iban\Import\Importers\CentralBankOfMontenegroImporter;
use Daycry\Iban\Import\Importers\CroatianNationalBankImporter;
use Daycry\Iban\Import\Importers\CzechNationalBankImporter;
use Daycry\Iban\Import\Importers\EpcRegisterImporter;
use Daycry\Iban\Import\Importers\EstonianBankingAssociationImporter;
use Daycry\Iban\Import\Importers\HellenicBankAssociationImporter;
use Daycry\Iban\Import\Importers\LiechtensteinImporter;
use Daycry\Iban\Import\Importers\LuxembourgBankersAssociationImporter;
use Daycry\Iban\Import\Importers\MagyarNemzetiBankImporter;
use Daycry\Iban\Import\Importers\NationalBankOfBelgiumImporter;
use Daycry\Iban\Import\Importers\NationalBankOfGeorgiaImporter;
use Daycry\Iban\Import\Importers\NationalBankOfKazakhstanImporter;
use Daycry\Iban\Import\Importers\NationalBankOfMoldovaImporter;
use Daycry\Iban\Import\Importers\NationalBankOfPolandImporter;
use Daycry\Iban\Import\Importers\NationalBankOfSlovakiaImporter;
use Daycry\Iban\Import\Importers\NationalBankOfUkraineImporter;
use Daycry\Iban\Import\Importers\NbrmImporter;
use Daycry\Iban\Import\Importers\OenbImporter;
use Daycry\Iban\Import\Importers\RegafiImporter;
use Daycry\Iban\Import\Importers\SixImporter;
use Daycry\Iban\Import\Importers\SwedenBankInfrastructureImporter;
use Daycry\Iban\Import\Importers\VaticanCityImporter;
use PHPUnit\Framework\TestCase;
use Tests\_support\FakeAtImporter;

/**
 * Exercises `ImporterRegistry`'s register/forCountry/get/sources API (V-6)
 * against the fake test importer -- framework-free, plain PHPUnit (the
 * registry itself has no CI4 dependency).
 *
 * @see \Daycry\Iban\Import\ImporterRegistry
 */
final class ImporterRegistryTest extends TestCase
{
    /**
     * v1.1's V-6 shipped the importer *framework* only, with an
     * intentionally empty `registerDefaults()`. v1.1's V-7a filled it in
     * with the first two bundled official-source importers --
     * {@see OenbImporter} (AT) and {@see BundesbankImporter} (DE). v1.1's
     * V-7b added three more -- {@see SixImporter} (CH),
     * {@see BetaalverenigingImporter} (NL) and {@see BancoDeEspanaImporter}
     * (ES). v1.2 added four more -- {@see CzechNationalBankImporter} (CZ),
     * {@see HellenicBankAssociationImporter} (GR),
     * {@see BankOfSloveniaImporter} (SI) and
     * {@see NationalBankOfSlovakiaImporter} (SK) -- and this v1.2 follow-up
     * batch adds four more XML-sourced importers --
     * {@see BulgarianNationalBankImporter} (BG),
     * {@see NationalBankOfMoldovaImporter} (MD),
     * {@see NationalBankOfPolandImporter} (PL) and
     * {@see CentralBankOfAzerbaijanImporter} (AZ) -- and this v1.2 BE/HR/LU/MT
     * batch adds four more, XLSX-sourced importers --
     * {@see NationalBankOfBelgiumImporter} (BE),
     * {@see CroatianNationalBankImporter} (HR),
     * {@see LuxembourgBankersAssociationImporter} (LU) and
     * {@see CentralBankOfMaltaImporter} (MT) -- and this v1.2 HU/NO/GE batch
     * adds three more, also XLSX-sourced -- {@see MagyarNemzetiBankImporter}
     * (HU), {@see BitsNorwayImporter} (NO) and
     * {@see NationalBankOfGeorgiaImporter} (GE) -- and this v1.2 IL/UA/KZ
     * batch adds three more, JSON-sourced importers --
     * {@see BankOfIsraelImporter} (IL), {@see NationalBankOfUkraineImporter}
     * (UA) and {@see NationalBankOfKazakhstanImporter} (KZ) -- and the v1.2
     * BR/LI batch adds two more -- {@see LiechtensteinImporter} (LI) and
     * {@see BrazilianCentralBankImporter} (BR) -- and this v1.2 EPC SEPA
     * Register batch registers {@see EpcRegisterImporter} five times -- once
     * each for GB, GI, IE, LV and RO -- so a plain `new ImporterRegistry()`
     * now finds all thirty without any extra registration.
     */
    public function testDefaultConstructionRegistersTheThirtyBundledImporters(): void
    {
        $registry = new ImporterRegistry();

        $all = $registry->all();

        self::assertCount(40, $all);
        self::assertInstanceOf(OenbImporter::class, $all[0]);
        self::assertInstanceOf(BundesbankImporter::class, $all[1]);
        self::assertInstanceOf(SixImporter::class, $all[2]);
        self::assertInstanceOf(BetaalverenigingImporter::class, $all[3]);
        self::assertInstanceOf(BancoDeEspanaImporter::class, $all[4]);
        self::assertInstanceOf(CzechNationalBankImporter::class, $all[5]);
        self::assertInstanceOf(HellenicBankAssociationImporter::class, $all[6]);
        self::assertInstanceOf(BankOfSloveniaImporter::class, $all[7]);
        self::assertInstanceOf(NationalBankOfSlovakiaImporter::class, $all[8]);
        self::assertInstanceOf(BulgarianNationalBankImporter::class, $all[9]);
        self::assertInstanceOf(NationalBankOfMoldovaImporter::class, $all[10]);
        self::assertInstanceOf(NationalBankOfPolandImporter::class, $all[11]);
        self::assertInstanceOf(CentralBankOfAzerbaijanImporter::class, $all[12]);
        self::assertInstanceOf(NationalBankOfBelgiumImporter::class, $all[13]);
        self::assertInstanceOf(CroatianNationalBankImporter::class, $all[14]);
        self::assertInstanceOf(LuxembourgBankersAssociationImporter::class, $all[15]);
        self::assertInstanceOf(CentralBankOfMaltaImporter::class, $all[16]);
        self::assertInstanceOf(MagyarNemzetiBankImporter::class, $all[17]);
        self::assertInstanceOf(BitsNorwayImporter::class, $all[18]);
        self::assertInstanceOf(NationalBankOfGeorgiaImporter::class, $all[19]);
        self::assertInstanceOf(BankOfIsraelImporter::class, $all[20]);
        self::assertInstanceOf(NationalBankOfUkraineImporter::class, $all[21]);
        self::assertInstanceOf(NationalBankOfKazakhstanImporter::class, $all[22]);
        self::assertInstanceOf(LiechtensteinImporter::class, $all[23]);
        self::assertInstanceOf(BrazilianCentralBankImporter::class, $all[24]);
        self::assertInstanceOf(EpcRegisterImporter::class, $all[25]);
        self::assertInstanceOf(EpcRegisterImporter::class, $all[26]);
        self::assertInstanceOf(EpcRegisterImporter::class, $all[27]);
        self::assertInstanceOf(EpcRegisterImporter::class, $all[28]);
        self::assertInstanceOf(EpcRegisterImporter::class, $all[29]);
        self::assertInstanceOf(SwedenBankInfrastructureImporter::class, $all[30]);
        self::assertInstanceOf(RegafiImporter::class, $all[31]);
        self::assertInstanceOf(RegafiImporter::class, $all[32]);
        self::assertInstanceOf(EstonianBankingAssociationImporter::class, $all[33]);
        self::assertInstanceOf(CentralBankOfMontenegroImporter::class, $all[34]);
        self::assertInstanceOf(CentralBankOfCyprusImporter::class, $all[35]);
        self::assertInstanceOf(AndorranBankingImporter::class, $all[36]);
        self::assertInstanceOf(BancoDePortugalImporter::class, $all[37]);
        self::assertInstanceOf(NbrmImporter::class, $all[38]);
        self::assertInstanceOf(VaticanCityImporter::class, $all[39]);

        self::assertSame([
            ['country' => 'AT', 'source' => 'oenb', 'name' => 'Oesterreichische Nationalbank', 'license' => 'CC-BY-4.0 (OeNB)'],
            ['country' => 'DE', 'source' => 'bundesbank', 'name' => 'Deutsche Bundesbank', 'license' => 'Deutsche Bundesbank'],
            ['country' => 'CH', 'source' => 'six', 'name' => 'SIX Interbank Clearing', 'license' => 'SIX Interbank Clearing (free use)'],
            ['country' => 'NL', 'source' => 'betaalvereniging', 'name' => 'Betaalvereniging Nederland', 'license' => 'Betaalvereniging Nederland (see terms)'],
            ['country' => 'ES', 'source' => 'bde', 'name' => 'Banco de España', 'license' => 'Banco de España'],
            ['country' => 'CZ', 'source' => 'cnb', 'name' => 'Czech National Bank', 'license' => 'Czech National Bank (cite source, no changes)'],
            ['country' => 'GR', 'source' => 'hba', 'name' => 'Hellenic Bank Association (HEBIC)', 'license' => 'Hellenic Bank Association (HEBIC)'],
            ['country' => 'SI', 'source' => 'bsi', 'name' => 'Bank of Slovenia', 'license' => 'Bank of Slovenia (cite source, no changes)'],
            ['country' => 'SK', 'source' => 'nbs', 'name' => 'National Bank of Slovakia', 'license' => 'National Bank of Slovakia'],
            ['country' => 'BG', 'source' => 'bnb', 'name' => 'Bulgarian National Bank', 'license' => 'Bulgarian National Bank'],
            ['country' => 'MD', 'source' => 'bnm', 'name' => 'National Bank of Moldova', 'license' => 'National Bank of Moldova'],
            ['country' => 'PL', 'source' => 'nbp', 'name' => 'Narodowy Bank Polski (EWIB)', 'license' => 'Narodowy Bank Polski (public sector information, free reuse)'],
            ['country' => 'AZ', 'source' => 'cbar', 'name' => 'Central Bank of Azerbaijan', 'license' => 'Central Bank of Azerbaijan'],
            ['country' => 'BE', 'source' => 'nbb', 'name' => 'National Bank of Belgium', 'license' => 'National Bank of Belgium'],
            ['country' => 'HR', 'source' => 'hnb', 'name' => 'Croatian National Bank', 'license' => 'Croatian National Bank (cite source, no changes)'],
            ['country' => 'LU', 'source' => 'abbl', 'name' => 'ABBL (Luxembourg Register of IBAN/BIC)', 'license' => 'ABBL Luxembourg IBAN/BIC Register'],
            ['country' => 'MT', 'source' => 'cbm', 'name' => 'Central Bank of Malta', 'license' => 'Central Bank of Malta'],
            ['country' => 'HU', 'source' => 'mnb', 'name' => 'Magyar Nemzeti Bank', 'license' => 'Magyar Nemzeti Bank'],
            ['country' => 'NO', 'source' => 'bits', 'name' => 'Bits AS (Norway)', 'license' => 'Bits AS (Norway)'],
            ['country' => 'GE', 'source' => 'nbg', 'name' => 'National Bank of Georgia', 'license' => 'National Bank of Georgia'],
            ['country' => 'IL', 'source' => 'boi', 'name' => 'Bank of Israel (data.gov.il)', 'license' => 'Bank of Israel (data.gov.il, other-open)'],
            ['country' => 'UA', 'source' => 'nbu', 'name' => 'National Bank of Ukraine', 'license' => 'National Bank of Ukraine (open data)'],
            ['country' => 'KZ', 'source' => 'nbk', 'name' => 'National Bank of Kazakhstan', 'license' => 'National Bank of Kazakhstan (open data)'],
            ['country' => 'LI', 'source' => 'six', 'name' => 'SIX Interbank Clearing (Liechtenstein)', 'license' => 'SIX Interbank Clearing (free use)'],
            ['country' => 'BR', 'source' => 'bcb', 'name' => 'Banco Central do Brasil', 'license' => 'Banco Central do Brasil (ODbL)'],
            ['country' => 'GB', 'source' => 'epc', 'name' => 'European Payments Council (SEPA Register)', 'license' => 'EPC SEPA Register (credit EPC, no resale as-is)'],
            ['country' => 'GI', 'source' => 'epc', 'name' => 'European Payments Council (SEPA Register)', 'license' => 'EPC SEPA Register (credit EPC, no resale as-is)'],
            ['country' => 'IE', 'source' => 'epc', 'name' => 'European Payments Council (SEPA Register)', 'license' => 'EPC SEPA Register (credit EPC, no resale as-is)'],
            ['country' => 'LV', 'source' => 'epc', 'name' => 'European Payments Council (SEPA Register)', 'license' => 'EPC SEPA Register (credit EPC, no resale as-is)'],
            ['country' => 'RO', 'source' => 'epc', 'name' => 'European Payments Council (SEPA Register)', 'license' => 'EPC SEPA Register (credit EPC, no resale as-is)'],
            ['country' => 'SE', 'source' => 'bankinfrastruktur', 'name' => 'Bankinfrastruktur BankData', 'license' => 'MIT (Bankinfrastruktur BankData)'],
            ['country' => 'FR', 'source' => 'regafi', 'name' => 'REGAFI (ACPR / Banque de France)', 'license' => 'Licence Ouverte / Etalab (attribution)'],
            ['country' => 'MC', 'source' => 'regafi', 'name' => 'REGAFI (ACPR / Banque de France)', 'license' => 'Licence Ouverte / Etalab (attribution)'],
            ['country' => 'EE', 'source' => 'pangaliit', 'name' => 'Eesti Pangaliit (Estonian Banking Association)', 'license' => 'Eesti Pangaliit (factual list)'],
            ['country' => 'ME', 'source' => 'cbcg', 'name' => 'Central Bank of Montenegro', 'license' => 'Central Bank of Montenegro'],
            ['country' => 'CY', 'source' => 'cbc', 'name' => 'Central Bank of Cyprus', 'license' => 'Central Bank of Cyprus (Terms of Use, attribution)'],
            ['country' => 'AD', 'source' => 'andorran-banking', 'name' => 'Andorran Banking (curated)', 'license' => 'curated (factual, non-copyrightable)'],
            ['country' => 'PT', 'source' => 'bportugal', 'name' => 'Banco de Portugal (SICOI)', 'license' => 'Banco de Portugal (attribution)'],
            ['country' => 'MK', 'source' => 'nbrm', 'name' => 'NBRM (National Bank of North Macedonia)', 'license' => 'NBRM (regulatory roster)'],
            ['country' => 'VA', 'source' => 'vatican', 'name' => 'Vatican City / IOR (curated)', 'license' => 'curated (factual, non-copyrightable)'],
        ], $registry->sources());

        self::assertNotNull($registry->get('AT', 'oenb'));
        self::assertNotNull($registry->get('DE', 'bundesbank'));
        self::assertNotNull($registry->get('CH', 'six'));
        self::assertNotNull($registry->get('NL', 'betaalvereniging'));
        self::assertNotNull($registry->get('ES', 'bde'));
        self::assertNotNull($registry->get('CZ', 'cnb'));
        self::assertNotNull($registry->get('GR', 'hba'));
        self::assertNotNull($registry->get('SI', 'bsi'));
        self::assertNotNull($registry->get('SK', 'nbs'));
        self::assertNotNull($registry->get('BG', 'bnb'));
        self::assertNotNull($registry->get('MD', 'bnm'));
        self::assertNotNull($registry->get('PL', 'nbp'));
        self::assertNotNull($registry->get('AZ', 'cbar'));
        self::assertNotNull($registry->get('BE', 'nbb'));
        self::assertNotNull($registry->get('HR', 'hnb'));
        self::assertNotNull($registry->get('LU', 'abbl'));
        self::assertNotNull($registry->get('MT', 'cbm'));
        self::assertNotNull($registry->get('HU', 'mnb'));
        self::assertNotNull($registry->get('NO', 'bits'));
        self::assertNotNull($registry->get('GE', 'nbg'));
        self::assertNotNull($registry->get('IL', 'boi'));
        self::assertNotNull($registry->get('UA', 'nbu'));
        self::assertNotNull($registry->get('KZ', 'nbk'));
        self::assertNotNull($registry->get('LI', 'six'));
        self::assertNotNull($registry->get('BR', 'bcb'));
        self::assertNotNull($registry->get('GB', 'epc'));
        self::assertNotNull($registry->get('GI', 'epc'));
        self::assertNotNull($registry->get('IE', 'epc'));
        self::assertNotNull($registry->get('LV', 'epc'));
        self::assertNotNull($registry->get('RO', 'epc'));
        self::assertNotNull($registry->get('SE', 'bankinfrastruktur'));
        self::assertNotNull($registry->get('FR', 'regafi'));
        self::assertNotNull($registry->get('MC', 'regafi'));
        self::assertNotNull($registry->get('EE', 'pangaliit'));
        self::assertNotNull($registry->get('ME', 'cbcg'));
        self::assertNotNull($registry->get('CY', 'cbc'));
        self::assertNotNull($registry->get('AD', 'andorran-banking'));
        self::assertNotNull($registry->get('PT', 'bportugal'));
        self::assertNotNull($registry->get('MK', 'nbrm'));
        self::assertNotNull($registry->get('VA', 'vatican'));

        // FR and MC share the 'regafi' sourceId but are keyed separately by
        // country, so both coexist as distinct registry entries.
        self::assertNotSame($registry->get('FR', 'regafi'), $registry->get('MC', 'regafi'));

        // LI and CH share the 'six' sourceId but are keyed separately by
        // country, so both coexist as distinct registry entries.
        self::assertNotSame($registry->get('CH', 'six'), $registry->get('LI', 'six'));
    }

    public function testRegisterAddsAnImporterFindableViaAll(): void
    {
        $registry = new ImporterRegistry();
        $importer = new FakeAtImporter();

        $registry->register($importer);

        // assertContains rather than assertSame([$importer], ...): the
        // registry now also carries the bundled AT/DE defaults (V-7a).
        self::assertContains($importer, $registry->all());
    }

    public function testForCountryFiltersByCountryCodeCaseInsensitively(): void
    {
        $registry = new ImporterRegistry();
        $importer = new FakeAtImporter();

        $registry->register($importer);

        // assertContains/assertNotContains rather than exact-array equality:
        // 'AT' now also matches the bundled OenbImporter default, and 'DE'
        // now matches the bundled BundesbankImporter default (V-7a).
        self::assertContains($importer, $registry->forCountry('AT'));
        self::assertContains($importer, $registry->forCountry('at'));
        self::assertNotContains($importer, $registry->forCountry('DE'));
    }

    public function testGetLooksUpByExactCountryAndSourceCaseInsensitively(): void
    {
        $registry = new ImporterRegistry();
        $importer = new FakeAtImporter();

        $registry->register($importer);

        self::assertSame($importer, $registry->get('AT', 'fake'));
        self::assertSame($importer, $registry->get('at', 'FAKE'));
        self::assertNull($registry->get('AT', 'other-source'));
        self::assertNull($registry->get('DE', 'fake'));
    }

    public function testRegisteringTheSameCountryAndSourceAgainReplacesTheEntry(): void
    {
        $registry = new ImporterRegistry();
        $first    = new FakeAtImporter();
        $second   = new FakeAtImporter();

        $registry->register($first);
        $registry->register($second);

        $all = $registry->all();

        self::assertNotContains($first, $all, 'Re-registering the same (country, source) must replace, not duplicate.');
        self::assertContains($second, $all);
        self::assertSame($second, $registry->get('AT', 'fake'));
    }

    public function testSourcesSummarizesEveryRegisteredImporterWithoutExposingInstances(): void
    {
        $registry = new ImporterRegistry();
        $registry->register(new FakeAtImporter());

        self::assertContains([
            'country' => 'AT',
            'source'  => 'fake',
            'name'    => 'Fake Test Importer',
            'license' => 'Public Domain (test fixture)',
        ], $registry->sources());
    }

    public function testAllReturnsImportersInRegistrationOrder(): void
    {
        $registry = new ImporterRegistry();

        $at = new FakeAtImporter();
        $de = new class () implements ImporterInterface {
            public function countryCode(): string
            {
                return 'DE';
            }

            public function sourceId(): string
            {
                return 'fake';
            }

            public function sourceName(): string
            {
                return 'Fake DE Importer';
            }

            public function license(): string
            {
                return 'Public Domain (test fixture)';
            }

            public function sourceUrl(): string
            {
                return 'https://example.test/fake-de-importer';
            }

            public function rows(?string $localFile = null): iterable
            {
                return [];
            }
        };

        $registry->register($at);
        $registry->register($de);

        // Registration order is preserved: the bundled defaults (V-7a +
        // V-7b + v1.2 + v1.2 follow-up + v1.2 BE/HR/LU/MT batch + v1.2
        // HU/NO/GE batch + v1.2 IL/UA/KZ batch + v1.2 BR/LI batch + v1.2
        // EPC SEPA Register batch + this v2.x SEPA-coverage batch) register
        // first (in the constructor), then $at and $de.
        $all = $registry->all();

        self::assertCount(42, $all);
        self::assertInstanceOf(OenbImporter::class, $all[0]);
        self::assertInstanceOf(BundesbankImporter::class, $all[1]);
        self::assertInstanceOf(SixImporter::class, $all[2]);
        self::assertInstanceOf(BetaalverenigingImporter::class, $all[3]);
        self::assertInstanceOf(BancoDeEspanaImporter::class, $all[4]);
        self::assertInstanceOf(CzechNationalBankImporter::class, $all[5]);
        self::assertInstanceOf(HellenicBankAssociationImporter::class, $all[6]);
        self::assertInstanceOf(BankOfSloveniaImporter::class, $all[7]);
        self::assertInstanceOf(NationalBankOfSlovakiaImporter::class, $all[8]);
        self::assertInstanceOf(BulgarianNationalBankImporter::class, $all[9]);
        self::assertInstanceOf(NationalBankOfMoldovaImporter::class, $all[10]);
        self::assertInstanceOf(NationalBankOfPolandImporter::class, $all[11]);
        self::assertInstanceOf(CentralBankOfAzerbaijanImporter::class, $all[12]);
        self::assertInstanceOf(NationalBankOfBelgiumImporter::class, $all[13]);
        self::assertInstanceOf(CroatianNationalBankImporter::class, $all[14]);
        self::assertInstanceOf(LuxembourgBankersAssociationImporter::class, $all[15]);
        self::assertInstanceOf(CentralBankOfMaltaImporter::class, $all[16]);
        self::assertInstanceOf(MagyarNemzetiBankImporter::class, $all[17]);
        self::assertInstanceOf(BitsNorwayImporter::class, $all[18]);
        self::assertInstanceOf(NationalBankOfGeorgiaImporter::class, $all[19]);
        self::assertInstanceOf(BankOfIsraelImporter::class, $all[20]);
        self::assertInstanceOf(NationalBankOfUkraineImporter::class, $all[21]);
        self::assertInstanceOf(NationalBankOfKazakhstanImporter::class, $all[22]);
        self::assertInstanceOf(LiechtensteinImporter::class, $all[23]);
        self::assertInstanceOf(BrazilianCentralBankImporter::class, $all[24]);
        self::assertInstanceOf(EpcRegisterImporter::class, $all[25]);
        self::assertInstanceOf(EpcRegisterImporter::class, $all[26]);
        self::assertInstanceOf(EpcRegisterImporter::class, $all[27]);
        self::assertInstanceOf(EpcRegisterImporter::class, $all[28]);
        self::assertInstanceOf(EpcRegisterImporter::class, $all[29]);
        self::assertInstanceOf(SwedenBankInfrastructureImporter::class, $all[30]);
        self::assertInstanceOf(RegafiImporter::class, $all[31]);
        self::assertInstanceOf(RegafiImporter::class, $all[32]);
        self::assertInstanceOf(EstonianBankingAssociationImporter::class, $all[33]);
        self::assertInstanceOf(CentralBankOfMontenegroImporter::class, $all[34]);
        self::assertInstanceOf(CentralBankOfCyprusImporter::class, $all[35]);
        self::assertInstanceOf(AndorranBankingImporter::class, $all[36]);
        self::assertInstanceOf(BancoDePortugalImporter::class, $all[37]);
        self::assertInstanceOf(NbrmImporter::class, $all[38]);
        self::assertInstanceOf(VaticanCityImporter::class, $all[39]);
        self::assertSame($at, $all[40]);
        self::assertSame($de, $all[41]);
    }

    public function testAllReturnValueIsAListOfImporterInterfaceInstances(): void
    {
        $registry = new ImporterRegistry();
        $registry->register(new FakeAtImporter());

        foreach ($registry->all() as $importer) {
            self::assertInstanceOf(ImporterInterface::class, $importer);
        }
    }
}
