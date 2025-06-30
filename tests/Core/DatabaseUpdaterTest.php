<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Tests\Core;

use danielsreichenbach\GeoIP2Update\Console\ProgressHandler;
use danielsreichenbach\GeoIP2Update\Core\DatabaseUpdater;
use danielsreichenbach\GeoIP2Update\Core\Downloader;
use danielsreichenbach\GeoIP2Update\Core\Extractor;
use danielsreichenbach\GeoIP2Update\Core\FileManager;
use danielsreichenbach\GeoIP2Update\Event\DatabaseUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\EventDispatcher;
use danielsreichenbach\GeoIP2Update\Event\PostUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\PreUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\UpdateErrorEvent;
use danielsreichenbach\GeoIP2Update\Model\Config;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class DatabaseUpdaterTest extends TestCase
{
    private DatabaseUpdater $updater;
    private Downloader $downloader;
    private Extractor $extractor;
    private FileManager $fileManager;
    private EventDispatcher $eventDispatcher;
    private ProgressHandler $progressHandler;
    private Config $config;
    private string $root;

    protected function setUp(): void
    {
        $this->downloader = $this->createMock(Downloader::class);
        $this->extractor = $this->createMock(Extractor::class);
        $this->fileManager = $this->createMock(FileManager::class);
        $this->eventDispatcher = $this->createMock(EventDispatcher::class);
        $this->progressHandler = $this->createMock(ProgressHandler::class);

        $this->updater = new DatabaseUpdater(
            $this->downloader,
            $this->extractor,
            $this->fileManager
        );

        $this->updater->setEventDispatcher($this->eventDispatcher);
        $this->updater->setProgressHandler($this->progressHandler);

        $this->root = vfsStream::setup('root')->url();

        $this->config = $this->createMock(Config::class);
        $this->config->method('getDatabaseFolder')->willReturn($this->root);
        $this->config->method('getEditions')->willReturn(['GeoLite2-City', 'GeoLite2-Country']);
    }

    public function testUpdateWithAllDatabasesUpToDate(): void
    {
        // Create version files
        mkdir($this->root . '/GeoLite2-City');
        file_put_contents($this->root . '/GeoLite2-City/VERSION.txt', '2023-12-01');
        mkdir($this->root . '/GeoLite2-Country');
        file_put_contents($this->root . '/GeoLite2-Country/VERSION.txt', '2023-12-01');

        // Mock remote info
        $this->downloader->method('getRemoteInfo')
            ->willReturn(['edition_id' => 'GeoLite2-City', 'date' => '2023-12-01']);

        // Expect pre update event, database update events for each edition, and post update event
        $this->eventDispatcher->expects($this->exactly(4))
            ->method('dispatch')
            ->withConsecutive(
                [$this->isInstanceOf(PreUpdateEvent::class)],
                [$this->isInstanceOf(DatabaseUpdateEvent::class)], // GeoLite2-City
                [$this->isInstanceOf(DatabaseUpdateEvent::class)], // GeoLite2-Country
                [$this->isInstanceOf(PostUpdateEvent::class)]
            );

        $results = $this->updater->update($this->config);

        $this->assertCount(2, $results);
        $this->assertTrue($results['GeoLite2-City']['success']);
        $this->assertStringContainsString('up to date', $results['GeoLite2-City']['message']);
    }

    public function testUpdateWithNewVersionAvailable(): void
    {
        // Set up config with only one edition
        $this->config = $this->createMock(Config::class);
        $this->config->method('getDatabaseFolder')->willReturn($this->root);
        $this->config->method('getEditions')->willReturn(['GeoLite2-City']);

        // Create old version file
        mkdir($this->root . '/GeoLite2-City');
        file_put_contents($this->root . '/GeoLite2-City/VERSION.txt', '2023-11-01');

        // Mock remote info with newer version
        $this->downloader->method('getRemoteInfo')
            ->willReturn(['edition_id' => 'GeoLite2-City', 'date' => '2023-12-01']);

        // Mock download
        $archivePath = $this->root . '/temp.tar.gz';
        $this->downloader->method('download')
            ->willReturn($archivePath);

        // Mock file operations
        $this->fileManager->expects($this->once())
            ->method('writeFile')
            ->with($this->root . '/GeoLite2-City/VERSION.txt', '2023-12-01');

        $this->fileManager->expects($this->once())
            ->method('deleteFile')
            ->with($archivePath);

        // Expect progress handling
        $this->progressHandler->expects($this->once())
            ->method('startDownload')
            ->with('GeoLite2-City');

        $this->progressHandler->expects($this->once())
            ->method('completeDownload');

        $results = $this->updater->update($this->config);

        $this->assertTrue($results['GeoLite2-City']['success']);
        $this->assertStringContainsString('has been updated', $results['GeoLite2-City']['message']);
        $this->assertEquals('2023-11-01', $results['GeoLite2-City']['oldVersion']);
        $this->assertEquals('2023-12-01', $results['GeoLite2-City']['newVersion']);
    }

    public function testUpdateWithForceFlag(): void
    {
        // Create current version file
        mkdir($this->root . '/GeoLite2-City');
        file_put_contents($this->root . '/GeoLite2-City/VERSION.txt', '2023-12-01');

        // Mock remote info with same version
        $this->downloader->method('getRemoteInfo')
            ->willReturn(['edition_id' => 'GeoLite2-City', 'date' => '2023-12-01']);

        // Mock download (should happen because of force flag)
        $archivePath = $this->root . '/temp.tar.gz';
        $this->downloader->method('download')
            ->willReturn($archivePath);

        $results = $this->updater->update($this->config, true);

        $this->assertTrue($results['GeoLite2-City']['success']);
        $this->assertStringContainsString('has been updated', $results['GeoLite2-City']['message']);
    }

    public function testUpdateWithDownloadFailure(): void
    {
        // Set up config with only one edition
        $this->config = $this->createMock(Config::class);
        $this->config->method('getDatabaseFolder')->willReturn($this->root);
        $this->config->method('getEditions')->willReturn(['GeoLite2-City']);

        // Mock remote info
        $this->downloader->method('getRemoteInfo')
            ->willReturn(['edition_id' => 'GeoLite2-City', 'date' => '2023-12-01']);

        // Mock download failure
        $this->downloader->method('download')
            ->willReturn(null);

        // Expect progress failure
        $this->progressHandler->expects($this->once())
            ->method('failDownload')
            ->with('Download failed');

        $results = $this->updater->update($this->config);

        $this->assertFalse($results['GeoLite2-City']['success']);
        $this->assertStringContainsString('Failed to download', $results['GeoLite2-City']['message']);
    }

    public function testUpdateWithExtractionFailure(): void
    {
        // Set up config with only one edition
        $this->config = $this->createMock(Config::class);
        $this->config->method('getDatabaseFolder')->willReturn($this->root);
        $this->config->method('getEditions')->willReturn(['GeoLite2-City']);

        // Mock remote info
        $this->downloader->method('getRemoteInfo')
            ->willReturn(['edition_id' => 'GeoLite2-City', 'date' => '2023-12-01']);

        // Mock successful download
        $archivePath = $this->root . '/temp.tar.gz';
        // Create the file so file_exists() returns true
        file_put_contents($archivePath, 'dummy archive content');
        $this->downloader->method('download')
            ->willReturn($archivePath);

        // Mock extraction failure
        $this->extractor->method('extract')
            ->willThrowException(new \Exception('Extraction failed'));

        // Expect cleanup
        $this->fileManager->expects($this->once())
            ->method('deleteFile')
            ->with($archivePath);

        // We don't need specific event expectations here since we're testing the error flow

        $results = $this->updater->update($this->config);

        $this->assertFalse($results['GeoLite2-City']['success']);
        $this->assertStringContainsString('Extraction failed', $results['GeoLite2-City']['message']);
    }

    public function testUpdateWithEventListenerSkippingUpdate(): void
    {
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PreUpdateEvent::class))
            ->willReturnCallback(function (PreUpdateEvent $event) {
                $event->skipUpdate();
            });

        $results = $this->updater->update($this->config);

        $this->assertEmpty($results);
    }

    public function testUpdateWithEventListenerSkippingDatabase(): void
    {
        // Mock remote info
        $this->downloader->method('getRemoteInfo')
            ->willReturn(['edition_id' => 'GeoLite2-City', 'date' => '2023-12-01']);

        $this->eventDispatcher->expects($this->any())
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                if ($event instanceof DatabaseUpdateEvent) {
                    $event->skipDatabase();
                }
            });

        $results = $this->updater->update($this->config);

        $this->assertTrue($results['GeoLite2-City']['success']);
        $this->assertStringContainsString('skipped by event listener', $results['GeoLite2-City']['message']);
    }

    public function testUpdateWithErrorEventRetry(): void
    {
        // Set up config with only one edition
        $this->config = $this->createMock(Config::class);
        $this->config->method('getDatabaseFolder')->willReturn($this->root);
        $this->config->method('getEditions')->willReturn(['GeoLite2-City']);

        // Mock remote info
        $this->downloader->method('getRemoteInfo')
            ->willReturn(['edition_id' => 'GeoLite2-City', 'date' => '2023-12-01']);

        // Mock download failure then success
        $archivePath = $this->root . '/temp.tar.gz';
        $this->downloader->expects($this->exactly(2))
            ->method('download')
            ->willReturnOnConsecutiveCalls(
                $this->throwException(new \Exception('First attempt failed')),
                $archivePath
            );

        // Mock error event with retry
        $this->eventDispatcher->expects($this->any())
            ->method('dispatch')
            ->willReturnCallback(function ($event) {
                if ($event instanceof UpdateErrorEvent) {
                    $event->retry();
                }
            });

        $results = $this->updater->update($this->config);

        $this->assertTrue($results['GeoLite2-City']['success']);
    }

    public function testUpdateWithNoRemoteInfo(): void
    {
        // Mock no remote info
        $this->downloader->method('getRemoteInfo')
            ->willReturn(null);

        $results = $this->updater->update($this->config);

        $this->assertFalse($results['GeoLite2-City']['success']);
        $this->assertStringContainsString('Failed to get remote info', $results['GeoLite2-City']['message']);
    }
}
