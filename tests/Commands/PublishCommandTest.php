<?php

declare(strict_types=1);

namespace Tests\Commands;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\StreamFilterTrait;
use Daycry\Iban\Commands\PublishCommand;

/**
 * Exercises `iban:publish` (see `src/Commands/PublishCommand.php`) via its
 * `$targetPath` test-injection seam -- the same idea as
 * `UpdateCommand::$registry` in `tests/Commands/CommandsTest.php` -- so the
 * command can be run against a disposable temp file instead of a real app's
 * `Config/` directory.
 *
 * @see docs/superpowers/specs/2026-07-10-daycry-iban-v1-design.md
 */
final class PublishCommandTest extends CIUnitTestCase
{
    use StreamFilterTrait;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpStreamFilterTrait();

        $this->tempDir = sys_get_temp_dir() . '/iban_publish_test_' . uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->tearDownStreamFilterTrait();

        $this->removeDirectory($this->tempDir);

        parent::tearDown();
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    /**
     * Runs `PublishCommand` directly (mirroring `CommandsTest::runUpdateWith()`)
     * so the `$targetPath` seam can be assigned -- `service('commands')->run()`
     * always news the command fresh with no injection point.
     *
     * @param list<string>         $argv
     * @param array<string, mixed> $params
     *
     * @return array{0: int, 1: string} [exitCode, captured STDOUT/STDERR]
     */
    private function runPublish(array $argv, string $targetPath, array $params = []): array
    {
        service('superglobals')->setServer('argv', array_merge(['spark'], $argv));
        CLI::init();

        $command             = new PublishCommand(service('logger'), service('commands'));
        $command->targetPath = $targetPath;

        $this->resetStreamFilterBuffer();

        $exitCode = $command->run($params);

        return [$exitCode, $this->getStreamFilterBuffer()];
    }

    private function assertValidPhpSyntax(string $file): void
    {
        $output   = [];
        $exitCode = 0;

        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);

        self::assertSame(0, $exitCode, 'php -l reported a syntax error: ' . implode("\n", $output));
    }

    public function testPublishToNonExistentTargetWritesFileAndReturnsSuccess(): void
    {
        $target = $this->tempDir . '/Config/Iban.php';

        [$exit, $output] = $this->runPublish(['iban:publish'], $target);

        self::assertSame(EXIT_SUCCESS, $exit);
        self::assertFileExists($target);
        self::assertStringContainsString('Published Iban config to', $output);

        $content = file_get_contents($target);
        self::assertIsString($content);

        self::assertStringContainsString('namespace Config;', $content);
        self::assertStringContainsString('use Daycry\Iban\Config\Iban as BaseIban;', $content);
        self::assertStringContainsString('class Iban extends BaseIban', $content);

        foreach (['provider', 'defaultFormat', 'checkNationalByDefault', 'dbGroup', 'table', 'cacheTtl'] as $property) {
            self::assertStringContainsString($property, $content);
        }

        self::assertStringNotContainsString('namespace Daycry\Iban\Config;', $content);
        self::assertStringNotContainsString('extends BaseConfig', $content);

        $this->assertValidPhpSyntax($target);
    }

    public function testPublishWhenTargetExistsWithoutForceReturnsErrorAndLeavesFileUnchanged(): void
    {
        $target = $this->tempDir . '/Config/Iban.php';
        mkdir(dirname($target), 0755, true);
        file_put_contents($target, '<?php // sentinel, hand-customized app config');

        [$exit, $output] = $this->runPublish(['iban:publish'], $target);

        self::assertSame(EXIT_ERROR, $exit);
        self::assertStringContainsString('already exists', $output);
        self::assertSame('<?php // sentinel, hand-customized app config', file_get_contents($target));
    }

    public function testPublishWithForceCliOptionOverwritesExistingFile(): void
    {
        $target = $this->tempDir . '/Config/Iban.php';
        mkdir(dirname($target), 0755, true);
        file_put_contents($target, '<?php // sentinel, hand-customized app config');

        [$exit] = $this->runPublish(['iban:publish', '--force'], $target);

        self::assertSame(EXIT_SUCCESS, $exit);

        $content = file_get_contents($target);
        self::assertIsString($content);
        self::assertStringContainsString('class Iban extends BaseIban', $content);
    }

    public function testPublishWithForceParamOverwritesExistingFile(): void
    {
        $target = $this->tempDir . '/Config/Iban.php';
        mkdir(dirname($target), 0755, true);
        file_put_contents($target, '<?php // sentinel, hand-customized app config');

        [$exit] = $this->runPublish(['iban:publish'], $target, ['force' => true]);

        self::assertSame(EXIT_SUCCESS, $exit);

        $content = file_get_contents($target);
        self::assertIsString($content);
        self::assertStringContainsString('class Iban extends BaseIban', $content);
    }
}
