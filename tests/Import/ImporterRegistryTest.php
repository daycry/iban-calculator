<?php

declare(strict_types=1);

namespace Tests\Import;

use Daycry\Iban\Contracts\ImporterInterface;
use Daycry\Iban\Import\ImporterRegistry;
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
    public function testDefaultConstructionRegistersNoImportersInV6(): void
    {
        // v1.1's V-6 ships the importer *framework* only; V-7 fills in the
        // concrete official-source importers via registerDefaults().
        $registry = new ImporterRegistry();

        self::assertSame([], $registry->all());
        self::assertSame([], $registry->sources());
    }

    public function testRegisterAddsAnImporterFindableViaAll(): void
    {
        $registry = new ImporterRegistry();
        $importer = new FakeAtImporter();

        $registry->register($importer);

        self::assertSame([$importer], $registry->all());
    }

    public function testForCountryFiltersByCountryCodeCaseInsensitively(): void
    {
        $registry = new ImporterRegistry();
        $importer = new FakeAtImporter();

        $registry->register($importer);

        self::assertSame([$importer], $registry->forCountry('AT'));
        self::assertSame([$importer], $registry->forCountry('at'));
        self::assertSame([], $registry->forCountry('DE'));
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

        self::assertSame([$second], $registry->all(), 'Re-registering the same (country, source) must replace, not duplicate.');
        self::assertSame($second, $registry->get('AT', 'fake'));
    }

    public function testSourcesSummarizesEveryRegisteredImporterWithoutExposingInstances(): void
    {
        $registry = new ImporterRegistry();
        $registry->register(new FakeAtImporter());

        self::assertSame([
            [
                'country' => 'AT',
                'source'  => 'fake',
                'name'    => 'Fake Test Importer',
                'license' => 'Public Domain (test fixture)',
            ],
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

        self::assertSame([$at, $de], $registry->all());
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
