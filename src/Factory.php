<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update;

use Composer\Composer;
use Composer\IO\IOInterface;
use danielsreichenbach\GeoIP2Update\Console\ProgressHandler;
use danielsreichenbach\GeoIP2Update\Core\DatabaseUpdater;
use danielsreichenbach\GeoIP2Update\Core\Downloader;
use danielsreichenbach\GeoIP2Update\Core\Extractor;
use danielsreichenbach\GeoIP2Update\Core\FileManager;
use danielsreichenbach\GeoIP2Update\Event\EventDispatcher;

class Factory
{
    private static ?DatabaseUpdater $databaseUpdater = null;
    private static ?Downloader $downloader = null;
    private static ?Extractor $extractor = null;
    private static ?FileManager $fileManager = null;
    private static ?EventDispatcher $eventDispatcher = null;

    public static function createDatabaseUpdater(?EventDispatcher $eventDispatcher = null, ?IOInterface $io = null): DatabaseUpdater
    {
        if (null === self::$databaseUpdater) {
            self::$databaseUpdater = new DatabaseUpdater(
                self::createDownloader(),
                self::createExtractor(),
                self::createFileManager()
            );
        }

        if (null !== $eventDispatcher) {
            self::$databaseUpdater->setEventDispatcher($eventDispatcher);
        }

        if (null !== $io) {
            $progressHandler = new ProgressHandler($io);
            self::$databaseUpdater->setProgressHandler($progressHandler);
        }

        return self::$databaseUpdater;
    }

    public static function createDownloader(): Downloader
    {
        if (null === self::$downloader) {
            self::$downloader = new Downloader(
                self::createFileManager()
            );
        }

        return self::$downloader;
    }

    public static function createExtractor(): Extractor
    {
        if (null === self::$extractor) {
            self::$extractor = new Extractor(
                self::createFileManager()
            );
        }

        return self::$extractor;
    }

    public static function createFileManager(): FileManager
    {
        if (null === self::$fileManager) {
            self::$fileManager = new FileManager();
        }

        return self::$fileManager;
    }

    public static function createEventDispatcher(?Composer $composer = null, ?IOInterface $io = null): EventDispatcher
    {
        if (null === self::$eventDispatcher) {
            $composerDispatcher = null;
            if (null !== $composer) {
                $composerDispatcher = $composer->getEventDispatcher();
            }

            self::$eventDispatcher = new EventDispatcher($composerDispatcher, $io);
        }

        return self::$eventDispatcher;
    }

    public static function createProgressHandler(?IOInterface $io = null): ProgressHandler
    {
        return new ProgressHandler($io);
    }

    /**
     * Reset all instances (useful for testing).
     */
    public static function reset(): void
    {
        self::$databaseUpdater = null;
        self::$downloader = null;
        self::$extractor = null;
        self::$fileManager = null;
        self::$eventDispatcher = null;
    }
}
