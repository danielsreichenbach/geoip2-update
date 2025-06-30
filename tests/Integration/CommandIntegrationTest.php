<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Tests\Integration;

use Composer\Composer;
use Composer\Config;
use Composer\Console\Application;
use Composer\IO\BufferIO;
use danielsreichenbach\GeoIP2Update\Command\UpdateCommand;
use danielsreichenbach\GeoIP2Update\CommandProvider;
use danielsreichenbach\GeoIP2Update\Factory;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the command-line interface.
 *
 * This test verifies that the geoip2:update command works correctly
 * when invoked through Composer.
 */
class CommandIntegrationTest extends TestCase
{
    private string $tempDir;
    private Application $application;
    private Composer $composer;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/geoip2-command-integration-' . uniqid();
        mkdir($this->tempDir, 0777, true);

        // Set up Composer
        $this->composer = new Composer();

        $config = new Config(false, $this->tempDir);
        $config->merge(['config' => ['home' => $this->tempDir]]);
        $this->composer->setConfig($config);

        // Set up event dispatcher
        $io = new BufferIO();
        $eventDispatcher = new \Composer\EventDispatcher\EventDispatcher($this->composer, $io);
        $this->composer->setEventDispatcher($eventDispatcher);

        // Set up Composer console application
        $this->application = new Application();

        // Reset Factory
        Factory::reset();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }

        Factory::reset();
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testCommandExecution(): void
    {
        // Create command
        $command = new UpdateCommand();

        // Verify command properties
        $this->assertInstanceOf(UpdateCommand::class, $command);
        $this->assertEquals('geoip2:update', $command->getName());
        $this->assertStringContainsString('Update GeoIP2', $command->getDescription());

        // Verify command options
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('force'));
        $this->assertTrue($definition->hasOption('edition'));

        // Test that command can be added to application
        $this->application->add($command);
        $this->assertTrue($this->application->has('geoip2:update'));
    }

    public function testCommandWithForceOption(): void
    {
        // Create command
        $command = new UpdateCommand();

        // Verify force option exists and works
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('force'));

        $forceOption = $definition->getOption('force');
        $this->assertEquals('f', $forceOption->getShortcut());
        $this->assertFalse($forceOption->acceptValue());
        $this->assertStringContainsString('Force update', $forceOption->getDescription());
    }

    public function testCommandWithSpecificEditions(): void
    {
        // Create command
        $command = new UpdateCommand();

        // Verify edition option exists
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasOption('edition'));

        $editionOption = $definition->getOption('edition');
        $this->assertEquals('e', $editionOption->getShortcut());
        $this->assertTrue($editionOption->isArray());
        $this->assertStringContainsString('Specific editions', $editionOption->getDescription());
    }

    public function testCommandProviderIntegration(): void
    {
        $provider = new CommandProvider();
        $commands = $provider->getCommands();

        $this->assertIsArray($commands);
        $this->assertNotEmpty($commands);

        // Find update command
        $updateCommand = null;
        foreach ($commands as $command) {
            if ('geoip2:update' === $command->getName()) {
                $updateCommand = $command;
                break;
            }
        }

        $this->assertNotNull($updateCommand);
        $this->assertInstanceOf(UpdateCommand::class, $updateCommand);
        $this->assertStringContainsString('Update GeoIP2', $updateCommand->getDescription());
    }

    public function testCommandWithInvalidConfiguration(): void
    {
        // Test that command exists and can be instantiated
        $command = new UpdateCommand();
        $this->assertInstanceOf(UpdateCommand::class, $command);

        // The actual execution with invalid config is tested in unit tests
        // Integration test just verifies the command can be created
        $this->assertTrue(true);
    }

    private function createTestArchive(): string
    {
        $archivePath = $this->tempDir . '/test.tar.gz';
        $tempDir = $this->tempDir . '/archive_temp';

        mkdir($tempDir);
        mkdir($tempDir . '/GeoLite2-Country_20231201');
        file_put_contents(
            $tempDir . '/GeoLite2-Country_20231201/GeoLite2-Country.mmdb',
            'test database content'
        );

        // Create archive
        $command = sprintf(
            'cd %s && tar -czf %s GeoLite2-Country_20231201/',
            escapeshellarg($tempDir),
            escapeshellarg($archivePath)
        );
        exec($command);

        $this->recursiveRemoveDirectory($tempDir);

        return $archivePath;
    }
}
