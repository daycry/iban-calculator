<?php

declare(strict_types=1);

namespace Tests\Config;

use CodeIgniter\Config\Factories;
use CodeIgniter\Test\CIUnitTestCase;
use Daycry\Iban\Config\Iban as IbanConfig;
use Daycry\Iban\Config\Services;

/**
 * Guards the config-resolution contract behind `iban:publish`: the package
 * must read its config by the SHORT name `'Iban'` (not the package FQCN), so
 * a consuming app that published `app/Config/Iban.php`
 * (`namespace Config; class Iban extends \Daycry\Iban\Config\Iban`) actually
 * overrides the package default. CI4's `Factories::locateClass()` only prefers
 * the app's `Config\` namespace for a non-namespaced alias — resolving by the
 * FQCN silently ignores the published override.
 */
final class ServicesConfigResolutionTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        Factories::reset('config');

        parent::tearDown();
    }

    public function testResolvesThePackageConfigByDefault(): void
    {
        // With nothing published, the short-name resolution still finds the
        // package's own Config\Iban (via the file locator).
        $config = Services::config();

        self::assertInstanceOf(IbanConfig::class, $config);
        self::assertSame(IbanConfig::class, $config::class);
    }

    public function testHonorsAnAppPublishedConfigOverride(): void
    {
        // Stand-in for a published `Config\Iban extends \Daycry\Iban\Config\Iban`,
        // made resolvable under the short name 'Iban' (the same key the app
        // override resolves under via CI4's preferApp path). On the previous
        // FQCN-based code this injection had no effect and this test fails.
        $override = new class () extends IbanConfig {
            public string $provider = 'database';
            public string $table    = 'custom_banks';
        };

        Factories::injectMock('config', 'Iban', $override);

        $resolved = Services::config();

        self::assertSame($override, $resolved);
        self::assertSame('database', $resolved->provider);
        self::assertSame('custom_banks', $resolved->table);
    }
}
