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

use danielsreichenbach\GeoIP2Update\Config\ConfigBuilder;
use danielsreichenbach\GeoIP2Update\Core\DatabaseUpdater;
use danielsreichenbach\GeoIP2Update\Core\Downloader;
use danielsreichenbach\GeoIP2Update\Core\Extractor;
use danielsreichenbach\GeoIP2Update\Core\FileManager;
use danielsreichenbach\GeoIP2Update\Event\DatabaseUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\EventDispatcher;
use danielsreichenbach\GeoIP2Update\Event\PostUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\PreUpdateEvent;
use danielsreichenbach\GeoIP2Update\Factory;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the complete update workflow.
 *
 * This test verifies that all components work together correctly
 * to update GeoIP2 databases.
 */
class UpdateWorkflowIntegrationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/geoip2-workflow-integration-' . uniqid();
        mkdir($this->tempDir, 0777, true);

        // Reset Factory state
        Factory::reset();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }

        // Reset Factory state
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

    public function testCompleteUpdateWorkflow(): void
    {
        // Create configuration
        $configBuilder = new ConfigBuilder();
        $config = $configBuilder->build([
            'maxmind-account-id' => 'test-account',
            'maxmind-license-key' => 'test-license',
            'maxmind-database-editions' => ['GeoLite2-Country'],
            'maxmind-database-folder' => $this->tempDir,
        ], $this->tempDir);

        $this->assertTrue($config->isValid());

        // Create event dispatcher with listeners
        $eventDispatcher = new EventDispatcher();

        $events = [];
        $eventDispatcher->addListener('geoip2.pre_update', function (PreUpdateEvent $event) use (&$events) {
            $events[] = 'pre_update';
        });

        $eventDispatcher->addListener('geoip2.database_update', function (DatabaseUpdateEvent $event) use (&$events) {
            $events[] = 'database_update:' . $event->getEdition();
        });

        $eventDispatcher->addListener('geoip2.post_update', function (PostUpdateEvent $event) use (&$events) {
            $events[] = 'post_update';
        });

        // Create components
        $fileManager = new FileManager();
        $downloader = $this->createMock(Downloader::class);
        $extractor = new Extractor($fileManager);

        // Mock downloader to simulate successful download
        $downloader->method('getRemoteInfo')
            ->willReturn([
                'edition_id' => 'GeoLite2-Country',
                'date' => '2023-12-01',
            ]);

        $archivePath = $this->tempDir . '/test.tar.gz';
        $this->createTestArchive($archivePath);

        $downloader->method('download')
            ->willReturn($archivePath);

        // Create updater
        $updater = new DatabaseUpdater($downloader, $extractor, $fileManager);
        $updater->setEventDispatcher($eventDispatcher);

        // Run update
        $results = $updater->update($config);

        // Verify results
        $this->assertArrayHasKey('GeoLite2-Country', $results);
        $this->assertTrue($results['GeoLite2-Country']['success']);

        // Verify events were fired
        $this->assertContains('pre_update', $events);
        $this->assertContains('database_update:GeoLite2-Country', $events);
        $this->assertContains('post_update', $events);

        // Verify version file was created
        $versionFile = $this->tempDir . '/GeoLite2-Country/VERSION.txt';
        $this->assertFileExists($versionFile);
        $this->assertEquals('2023-12-01', file_get_contents($versionFile));
    }

    public function testUpdateWithExistingDatabase(): void
    {
        // Create existing database directory with version
        $dbDir = $this->tempDir . '/GeoLite2-Country';
        mkdir($dbDir, 0777, true);
        file_put_contents($dbDir . '/VERSION.txt', '2023-11-01');
        file_put_contents($dbDir . '/GeoLite2-Country.mmdb', 'old database content');

        // Create configuration
        $configBuilder = new ConfigBuilder();
        $config = $configBuilder->build([
            'maxmind-account-id' => 'test-account',
            'maxmind-license-key' => 'test-license',
            'maxmind-database-editions' => ['GeoLite2-Country'],
            'maxmind-database-folder' => $this->tempDir,
        ], $this->tempDir);

        // Mock downloader
        $downloader = $this->createMock(Downloader::class);
        $downloader->method('getRemoteInfo')
            ->willReturn([
                'edition_id' => 'GeoLite2-Country',
                'date' => '2023-12-01',
            ]);

        $archivePath = $this->tempDir . '/test.tar.gz';
        $this->createTestArchive($archivePath);

        $downloader->method('download')
            ->willReturn($archivePath);

        // Replace factory downloader BEFORE creating updater
        $reflection = new \ReflectionClass(Factory::class);
        $property = $reflection->getProperty('downloader');
        $property->setAccessible(true);
        $property->setValue(null, $downloader);

        // Create components using Factory
        $updater = Factory::createDatabaseUpdater();

        // Run update
        $results = $updater->update($config);

        // Verify update occurred
        if (!$results['GeoLite2-Country']['success']) {
            $this->fail('Update failed: ' . $results['GeoLite2-Country']['message']);
        }
        $this->assertTrue($results['GeoLite2-Country']['success']);
        $this->assertEquals('2023-11-01', $results['GeoLite2-Country']['oldVersion']);
        $this->assertEquals('2023-12-01', $results['GeoLite2-Country']['newVersion']);

        // Verify database was updated
        $this->assertEquals('2023-12-01', file_get_contents($dbDir . '/VERSION.txt'));
        $this->assertFileExists($dbDir . '/GeoLite2-Country.mmdb');
    }

    public function testUpdateWithEventModification(): void
    {
        // Create configuration
        $configBuilder = new ConfigBuilder();
        $config = $configBuilder->build([
            'maxmind-account-id' => 'test-account',
            'maxmind-license-key' => 'test-license',
            'maxmind-database-editions' => ['GeoLite2-Country', 'GeoLite2-City'],
            'maxmind-database-folder' => $this->tempDir,
        ], $this->tempDir);

        // Create event dispatcher
        $eventDispatcher = new EventDispatcher();

        // Add listener that skips GeoLite2-City
        $eventDispatcher->addListener('geoip2.database_update', function (DatabaseUpdateEvent $event) {
            if ('GeoLite2-City' === $event->getEdition()) {
                $event->skipDatabase();
            }
        });

        // Create updater with mocked downloader
        $downloader = $this->createMock(Downloader::class);
        $downloader->method('getRemoteInfo')
            ->willReturnCallback(function ($edition) {
                return [
                    'edition_id' => $edition,
                    'date' => '2023-12-01',
                ];
            });

        $fileManager = new FileManager();
        $extractor = new Extractor($fileManager);
        $updater = new DatabaseUpdater($downloader, $extractor, $fileManager);
        $updater->setEventDispatcher($eventDispatcher);

        // Run update
        $results = $updater->update($config);

        // Verify GeoLite2-Country was checked
        $this->assertArrayHasKey('GeoLite2-Country', $results);

        // Verify GeoLite2-City was skipped
        $this->assertArrayHasKey('GeoLite2-City', $results);
        $this->assertTrue($results['GeoLite2-City']['success']);
        $this->assertStringContainsString('skipped by event listener', $results['GeoLite2-City']['message']);
    }

    public function testFactorySingletonBehavior(): void
    {
        // Create multiple instances through Factory
        $fileManager1 = Factory::createFileManager();
        $fileManager2 = Factory::createFileManager();

        $downloader1 = Factory::createDownloader();
        $downloader2 = Factory::createDownloader();

        // Verify singleton behavior
        $this->assertSame($fileManager1, $fileManager2);
        $this->assertSame($downloader1, $downloader2);

        // Reset and verify new instances
        Factory::reset();

        $fileManager3 = Factory::createFileManager();
        $downloader3 = Factory::createDownloader();

        $this->assertNotSame($fileManager1, $fileManager3);
        $this->assertNotSame($downloader1, $downloader3);
    }

    private function createTestArchive(string $path): void
    {
        $tempDir = dirname($path) . '/archive_temp';
        mkdir($tempDir);
        mkdir($tempDir . '/GeoLite2-Country_20231201');
        file_put_contents(
            $tempDir . '/GeoLite2-Country_20231201/GeoLite2-Country.mmdb',
            'test database content'
        );

        // Create archive using tar command
        $command = sprintf(
            'cd %s && tar -czf %s GeoLite2-Country_20231201/',
            escapeshellarg($tempDir),
            escapeshellarg($path)
        );
        exec($command);

        $this->recursiveRemoveDirectory($tempDir);
    }
}
