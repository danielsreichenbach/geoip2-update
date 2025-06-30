<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Tests\Command;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use danielsreichenbach\GeoIP2Update\Command\UpdateCommand;
use danielsreichenbach\GeoIP2Update\Core\DatabaseUpdater;
use danielsreichenbach\GeoIP2Update\Factory;
use danielsreichenbach\GeoIP2Update\GeoIP2UpdatePlugin;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class UpdateCommandTest extends TestCase
{
    private UpdateCommand $command;
    private Composer $composer;
    private IOInterface $io;
    private string $tempDir;
    private DatabaseUpdater $updater;

    protected function setUp(): void
    {
        $this->composer = $this->createMock(Composer::class);
        $this->io = $this->createMock(IOInterface::class);

        // Create a temporary test directory first
        $this->tempDir = sys_get_temp_dir() . '/geoip2-update-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);

        // Create a composer.json in the temp dir to prevent global config lookup
        file_put_contents($this->tempDir . '/composer.json', '{}');

        // Create an anonymous class that extends Composer's Config
        $config = new class($this->tempDir) extends Config {
            private string $baseDir;

            public function __construct(string $baseDir)
            {
                $this->baseDir = $baseDir;
                parent::__construct();
            }

            public function get($key, int $flags = 0)
            {
                if ('home' === $key) {
                    return $this->baseDir;
                }

                return parent::get($key, $flags);
            }
        };

        // Don't set up a default root package - let each test configure it
        $this->composer->method('getConfig')->willReturn($config);

        // Create command and inject dependencies
        $this->command = new UpdateCommand();
        $this->command->setComposer($this->composer);
        $this->command->setIO($this->io);

        // Mock the DatabaseUpdater
        $this->updater = $this->createMock(DatabaseUpdater::class);
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (isset($this->tempDir) && is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }

        // Reset Factory instances
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

    public function testConfigure(): void
    {
        $this->assertEquals('geoip2:update', $this->command->getName());
        $this->assertStringContainsString('Update GeoIP2/GeoLite2 databases', $this->command->getDescription());

        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('force'));
        $this->assertTrue($definition->hasOption('edition'));
    }

    public function testExecuteWithNoConfiguration(): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([]);

        $this->composer->method('getPackage')->willReturn($rootPackage);

        $this->io->expects($this->once())
            ->method('writeError')
            ->with('<error>No GeoIP2 Update configuration found.</error>');

        // Expect multiple write calls for the configuration example
        $this->io->expects($this->atLeast(2))
            ->method('write');

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $this->command->run($input, $output);

        $this->assertEquals(1, $exitCode);
    }

    public function testExecuteWithInvalidConfiguration(): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([
            GeoIP2UpdatePlugin::EXTRA_KEY => [
                'maxmind-account-id' => '',
                'maxmind-license-key' => '',
            ],
        ]);

        $this->composer->method('getPackage')->willReturn($rootPackage);

        // Capture all writeError calls to debug
        $errors = [];
        $this->io->expects($this->any())
            ->method('writeError')
            ->willReturnCallback(function ($message) use (&$errors) {
                $errors[] = $message;
            });

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $this->command->run($input, $output);

        // Debug output
        if (!in_array('<error>GeoIP2 Update configuration is invalid.</error>', $errors, true)) {
            $this->fail('Expected error not found. Actual errors: ' . implode(', ', $errors));
        }

        $this->assertEquals(1, $exitCode);
    }

    public function testExecuteWithValidConfiguration(): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([
            GeoIP2UpdatePlugin::EXTRA_KEY => [
                'maxmind-account-id' => '123456',
                'maxmind-license-key' => 'test-license-key',
                'maxmind-database-editions' => ['GeoLite2-Country', 'GeoLite2-City'],
                'maxmind-database-folder' => $this->tempDir,
            ],
        ]);

        $this->composer->method('getPackage')->willReturn($rootPackage);

        // Expect multiple write calls
        $this->io->expects($this->atLeastOnce())
            ->method('write')
            ->with($this->logicalOr(
                $this->stringContains('Updating GeoIP2 databases'),
                $this->stringContains('Account ID'),
                $this->stringContains('Database folder'),
                $this->stringContains('Editions'),
                $this->equalTo(''),
                $this->stringContains('Updated'),
                $this->stringContains('database')
            ));

        // Mock successful update
        $results = [
            'GeoLite2-Country' => ['success' => true, 'message' => 'Updated'],
            'GeoLite2-City' => ['success' => true, 'message' => 'Updated'],
        ];

        // Replace the factory instance with our mock
        $reflection = new \ReflectionClass(Factory::class);
        $databaseUpdaterProperty = $reflection->getProperty('databaseUpdater');
        $databaseUpdaterProperty->setAccessible(true);
        $databaseUpdaterProperty->setValue(null, $this->updater);

        $this->updater->method('update')->willReturn($results);

        $input = new ArrayInput([]);
        $output = new BufferedOutput();

        $exitCode = $this->command->run($input, $output);

        $this->assertEquals(0, $exitCode);
    }

    public function testExecuteWithSpecificEditions(): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([
            GeoIP2UpdatePlugin::EXTRA_KEY => [
                'maxmind-account-id' => '123456',
                'maxmind-license-key' => 'test-license-key',
                'maxmind-database-editions' => ['GeoLite2-Country', 'GeoLite2-City'],
                'maxmind-database-folder' => $this->tempDir,
            ],
        ]);

        $this->composer->method('getPackage')->willReturn($rootPackage);

        $this->io->expects($this->atLeastOnce())
            ->method('write')
            ->with($this->logicalOr(
                $this->stringContains('Editions: GeoLite2-ASN'),
                $this->anything()
            ));

        // Mock successful update
        $results = [
            'GeoLite2-ASN' => ['success' => true, 'message' => 'Updated'],
        ];

        // Replace the factory instance with our mock
        $reflection = new \ReflectionClass(Factory::class);
        $databaseUpdaterProperty = $reflection->getProperty('databaseUpdater');
        $databaseUpdaterProperty->setAccessible(true);
        $databaseUpdaterProperty->setValue(null, $this->updater);

        $this->updater->method('update')->willReturn($results);

        $input = new ArrayInput([
            '--edition' => ['GeoLite2-ASN'],
        ]);
        $output = new BufferedOutput();

        $exitCode = $this->command->run($input, $output);

        $this->assertEquals(0, $exitCode);
    }

    public function testExecuteWithForceOption(): void
    {
        $rootPackage = $this->createMock(RootPackageInterface::class);
        $rootPackage->method('getExtra')->willReturn([
            GeoIP2UpdatePlugin::EXTRA_KEY => [
                'maxmind-account-id' => '123456',
                'maxmind-license-key' => 'test-license-key',
                'maxmind-database-editions' => ['GeoLite2-Country'],
                'maxmind-database-folder' => $this->tempDir,
            ],
        ]);

        $this->composer->method('getPackage')->willReturn($rootPackage);

        // Mock successful update
        $results = [
            'GeoLite2-Country' => ['success' => true, 'message' => 'Updated'],
        ];

        // Replace the factory instance with our mock
        $reflection = new \ReflectionClass(Factory::class);
        $databaseUpdaterProperty = $reflection->getProperty('databaseUpdater');
        $databaseUpdaterProperty->setAccessible(true);
        $databaseUpdaterProperty->setValue(null, $this->updater);

        $this->updater->expects($this->once())
            ->method('update')
            ->with($this->anything(), true) // Should pass force=true
            ->willReturn($results);

        $input = new ArrayInput([
            '--force' => true,
        ]);
        $output = new BufferedOutput();

        $exitCode = $this->command->run($input, $output);

        $this->assertEquals(0, $exitCode);
    }
}
