<?php

declare(strict_types=1);

namespace Tests\Import;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\ImporterRegistry;
use Daycry\Iban\Import\Importers\BancoDeEspanaImporter;
use Daycry\Iban\Import\Importers\BetaalverenigingImporter;
use Daycry\Iban\Import\Importers\BundesbankImporter;
use Daycry\Iban\Import\Importers\OenbImporter;
use Daycry\Iban\Import\Importers\SixImporter;
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
     * {@see OenbImporter} (AT) and {@see BundesbankImporter} (DE) -- and
     * v1.1's V-7b adds three more -- {@see SixImporter} (CH),
     * {@see BetaalverenigingImporter} (NL) and {@see BancoDeEspanaImporter}
     * (ES) -- so a plain `new ImporterRegistry()` now finds all five
     * without any extra registration.
     */
    public function testDefaultConstructionRegistersTheFiveBundledImporters(): void
    {
        $registry = new ImporterRegistry();

        $all = $registry->all();

        self::assertCount(5, $all);
        self::assertInstanceOf(OenbImporter::class, $all[0]);
        self::assertInstanceOf(BundesbankImporter::class, $all[1]);
        self::assertInstanceOf(SixImporter::class, $all[2]);
        self::assertInstanceOf(BetaalverenigingImporter::class, $all[3]);
        self::assertInstanceOf(BancoDeEspanaImporter::class, $all[4]);

        self::assertSame([
            ['country' => 'AT', 'source' => 'oenb', 'name' => 'Oesterreichische Nationalbank', 'license' => 'CC-BY-4.0 (OeNB)'],
            ['country' => 'DE', 'source' => 'bundesbank', 'name' => 'Deutsche Bundesbank', 'license' => 'Deutsche Bundesbank'],
            ['country' => 'CH', 'source' => 'six', 'name' => 'SIX Interbank Clearing', 'license' => 'SIX Interbank Clearing (free use)'],
            ['country' => 'NL', 'source' => 'betaalvereniging', 'name' => 'Betaalvereniging Nederland', 'license' => 'Betaalvereniging Nederland (see terms)'],
            ['country' => 'ES', 'source' => 'bde', 'name' => 'Banco de España', 'license' => 'Banco de España'],
        ], $registry->sources());

        self::assertNotNull($registry->get('AT', 'oenb'));
        self::assertNotNull($registry->get('DE', 'bundesbank'));
        self::assertNotNull($registry->get('CH', 'six'));
        self::assertNotNull($registry->get('NL', 'betaalvereniging'));
        self::assertNotNull($registry->get('ES', 'bde'));
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

        // Registration order is preserved: the 5 bundled defaults (V-7a +
        // V-7b) register first (in the constructor), then $at and $de.
        $all = $registry->all();

        self::assertCount(7, $all);
        self::assertInstanceOf(OenbImporter::class, $all[0]);
        self::assertInstanceOf(BundesbankImporter::class, $all[1]);
        self::assertInstanceOf(SixImporter::class, $all[2]);
        self::assertInstanceOf(BetaalverenigingImporter::class, $all[3]);
        self::assertInstanceOf(BancoDeEspanaImporter::class, $all[4]);
        self::assertSame($at, $all[5]);
        self::assertSame($de, $all[6]);
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
