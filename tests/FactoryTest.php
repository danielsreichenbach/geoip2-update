<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Tests;

use Composer\Composer;
use Composer\IO\IOInterface;
use danielsreichenbach\GeoIP2Update\Console\ProgressHandler;
use danielsreichenbach\GeoIP2Update\Core\DatabaseUpdater;
use danielsreichenbach\GeoIP2Update\Core\Downloader;
use danielsreichenbach\GeoIP2Update\Core\Extractor;
use danielsreichenbach\GeoIP2Update\Core\FileManager;
use danielsreichenbach\GeoIP2Update\Event\EventDispatcher;
use danielsreichenbach\GeoIP2Update\Factory;
use PHPUnit\Framework\TestCase;

class FactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset singleton instances
        Factory::reset();
    }

    public function testCreateFileManager(): void
    {
        $fileManager1 = Factory::createFileManager();
        $fileManager2 = Factory::createFileManager();

        $this->assertInstanceOf(FileManager::class, $fileManager1);
        $this->assertSame($fileManager1, $fileManager2); // Should be singleton
    }

    public function testCreateDownloader(): void
    {
        $downloader1 = Factory::createDownloader();
        $downloader2 = Factory::createDownloader();

        $this->assertInstanceOf(Downloader::class, $downloader1);
        $this->assertSame($downloader1, $downloader2); // Should be singleton
    }

    public function testCreateExtractor(): void
    {
        $extractor1 = Factory::createExtractor();
        $extractor2 = Factory::createExtractor();

        $this->assertInstanceOf(Extractor::class, $extractor1);
        $this->assertSame($extractor1, $extractor2); // Should be singleton
    }

    public function testCreateDatabaseUpdater(): void
    {
        $updater1 = Factory::createDatabaseUpdater();
        $updater2 = Factory::createDatabaseUpdater();

        $this->assertInstanceOf(DatabaseUpdater::class, $updater1);
        $this->assertSame($updater1, $updater2); // Should be singleton
    }

    public function testCreateDatabaseUpdaterWithEventDispatcher(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $io = $this->createMock(IOInterface::class);

        $updater = Factory::createDatabaseUpdater($eventDispatcher, $io);

        $this->assertInstanceOf(DatabaseUpdater::class, $updater);

        // Test that the updater has event dispatcher and progress handler set
        $reflection = new \ReflectionClass($updater);

        $eventDispatcherProperty = $reflection->getProperty('eventDispatcher');
        $eventDispatcherProperty->setAccessible(true);
        $this->assertSame($eventDispatcher, $eventDispatcherProperty->getValue($updater));

        $progressHandlerProperty = $reflection->getProperty('progressHandler');
        $progressHandlerProperty->setAccessible(true);
        $this->assertInstanceOf(ProgressHandler::class, $progressHandlerProperty->getValue($updater));
    }

    public function testCreateEventDispatcher(): void
    {
        $composer = $this->createMock(Composer::class);
        $io = $this->createMock(IOInterface::class);

        $dispatcher1 = Factory::createEventDispatcher($composer, $io);
        $dispatcher2 = Factory::createEventDispatcher($composer, $io);

        $this->assertInstanceOf(EventDispatcher::class, $dispatcher1);
        $this->assertSame($dispatcher1, $dispatcher2); // Should be singleton
    }

    public function testCreateEventDispatcherWithoutParams(): void
    {
        $dispatcher = Factory::createEventDispatcher();

        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
    }

    public function testCreateProgressHandler(): void
    {
        $io = $this->createMock(IOInterface::class);

        $handler1 = Factory::createProgressHandler($io);
        $handler2 = Factory::createProgressHandler($io);

        $this->assertInstanceOf(ProgressHandler::class, $handler1);
        $this->assertNotSame($handler1, $handler2); // Not a singleton
    }

    public function testCreateProgressHandlerWithoutIO(): void
    {
        $handler = Factory::createProgressHandler();

        $this->assertInstanceOf(ProgressHandler::class, $handler);
    }

    public function testSingletonBehaviorAcrossDifferentCreations(): void
    {
        // Create instances in different orders
        $fileManager1 = Factory::createFileManager();
        $downloader1 = Factory::createDownloader();
        $extractor1 = Factory::createExtractor();

        // Create again
        $fileManager2 = Factory::createFileManager();
        $downloader2 = Factory::createDownloader();
        $extractor2 = Factory::createExtractor();

        // All should be the same instances
        $this->assertSame($fileManager1, $fileManager2);
        $this->assertSame($downloader1, $downloader2);
        $this->assertSame($extractor1, $extractor2);

        // Dependencies should also be shared
        $reflection = new \ReflectionClass($downloader1);
        $fileManagerProperty = $reflection->getProperty('fileManager');
        $fileManagerProperty->setAccessible(true);

        $this->assertSame($fileManager1, $fileManagerProperty->getValue($downloader1));
    }
}
