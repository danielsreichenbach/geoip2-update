<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Core;

use danielsreichenbach\GeoIP2Update\Console\ProgressHandler;
use danielsreichenbach\GeoIP2Update\Event\DatabaseUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\EventDispatcher;
use danielsreichenbach\GeoIP2Update\Event\PostUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\PreUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\UpdateErrorEvent;
use danielsreichenbach\GeoIP2Update\Model\Config;

class DatabaseUpdater
{
    private const VERSION_FILE = 'VERSION.txt';
    private ?EventDispatcher $eventDispatcher = null;
    private ?ProgressHandler $progressHandler = null;

    public function __construct(
        private Downloader $downloader,
        private Extractor $extractor,
        private FileManager $fileManager,
    ) {
    }

    public function setEventDispatcher(?EventDispatcher $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function setProgressHandler(?ProgressHandler $progressHandler): void
    {
        $this->progressHandler = $progressHandler;
    }

    /**
     * @return array<string, array{success: bool, message: string, oldVersion?: string, newVersion?: string}>
     */
    public function update(Config $config, bool $force = false): array
    {
        $editions = $config->getEditions();

        // Dispatch pre-update event
        if (null !== $this->eventDispatcher) {
            $preUpdateEvent = new PreUpdateEvent($config, $editions, $force);
            $this->eventDispatcher->dispatch($preUpdateEvent);

            if ($preUpdateEvent->shouldSkipUpdate()) {
                return [];
            }

            // Allow event listeners to modify editions
            $editions = $preUpdateEvent->getEditions();
            $force = $preUpdateEvent->isForce();
        }

        $results = [];

        foreach ($editions as $edition) {
            try {
                $results[$edition] = $this->updateEdition($config, $edition, $force);
            } catch (\Exception $e) {
                // Dispatch error event
                if (null !== $this->eventDispatcher) {
                    $errorEvent = new UpdateErrorEvent($config, $edition, $e);
                    $this->eventDispatcher->dispatch($errorEvent);

                    if ($errorEvent->shouldRetry()) {
                        // Retry the update
                        try {
                            $results[$edition] = $this->updateEdition($config, $edition, $force);
                            continue;
                        } catch (\Exception $retryException) {
                            // Retry failed, use the retry exception
                            $e = $retryException;
                        }
                    }

                    if (!$errorEvent->shouldContinue()) {
                        // Stop processing remaining editions
                        $results[$edition] = [
                            'success' => false,
                            'message' => sprintf('Failed to update %s: %s', $edition, $e->getMessage()),
                        ];
                        break;
                    }
                }

                $results[$edition] = [
                    'success' => false,
                    'message' => sprintf('Failed to update %s: %s', $edition, $e->getMessage()),
                ];
            }
        }

        // Dispatch post-update event
        if (null !== $this->eventDispatcher) {
            $postUpdateEvent = new PostUpdateEvent($config, $results);
            $this->eventDispatcher->dispatch($postUpdateEvent);
        }

        return $results;
    }

    /**
     * @return array{success: bool, message: string, oldVersion?: string, newVersion?: string}
     */
    private function updateEdition(Config $config, string $edition, bool $force): array
    {
        // Get remote database info
        $remoteInfo = $this->downloader->getRemoteInfo($config, $edition);

        if (!$remoteInfo) {
            return [
                'success' => false,
                'message' => sprintf('Failed to get remote info for %s', $edition),
            ];
        }

        // Check local version
        $editionPath = $this->getEditionPath($config->getDatabaseFolder(), $edition);
        $currentVersion = $this->getCurrentVersion($editionPath);
        $remoteVersion = $remoteInfo['date'];

        // Dispatch database update event
        if (null !== $this->eventDispatcher) {
            $databaseEvent = new DatabaseUpdateEvent($config, $edition, $currentVersion, $remoteVersion);
            $this->eventDispatcher->dispatch($databaseEvent);

            if ($databaseEvent->shouldSkipDatabase()) {
                $result = [
                    'success' => true,
                    'message' => sprintf('%s update skipped by event listener', $edition),
                    'newVersion' => $remoteVersion,
                ];
                if (null !== $currentVersion) {
                    $result['oldVersion'] = $currentVersion;
                }

                return $result;
            }
        }

        // Compare versions
        if (!$force && $currentVersion && $currentVersion >= $remoteVersion) {
            $result = [
                'success' => true,
                'message' => sprintf('%s is up to date', $edition),
                'newVersion' => $remoteVersion,
            ];
            if (null !== $currentVersion) {
                $result['oldVersion'] = $currentVersion;
            }

            return $result;
        }

        // Set up progress callback if handler is available
        if (null !== $this->progressHandler) {
            $this->progressHandler->startDownload($edition);
            $this->downloader->setProgressCallback(function ($downloadSize, $downloaded) {
                $this->progressHandler->updateProgress($downloadSize, $downloaded);
            });
        }

        // Download new version
        $archivePath = $this->downloader->download($config, $edition, $remoteInfo);

        if (!$archivePath) {
            if (null !== $this->progressHandler) {
                $this->progressHandler->failDownload('Download failed');
            }

            return [
                'success' => false,
                'message' => sprintf('Failed to download %s', $edition),
            ];
        }

        if (null !== $this->progressHandler) {
            $this->progressHandler->completeDownload();
        }

        try {
            // Extract archive
            $this->extractor->extract($archivePath, $editionPath);

            // Update version file
            $this->fileManager->writeFile(
                $editionPath . DIRECTORY_SEPARATOR . self::VERSION_FILE,
                $remoteVersion
            );

            // Clean up archive
            $this->fileManager->deleteFile($archivePath);

            return [
                'success' => true,
                'message' => sprintf('%s has been updated', $edition),
                'oldVersion' => $currentVersion ?: 'none',
                'newVersion' => $remoteVersion,
            ];
        } catch (\Exception $e) {
            // Clean up on failure
            if (file_exists($archivePath)) {
                $this->fileManager->deleteFile($archivePath);
            }

            throw $e;
        }
    }

    private function getCurrentVersion(string $editionPath): ?string
    {
        $versionFile = $editionPath . DIRECTORY_SEPARATOR . self::VERSION_FILE;

        if (!file_exists($versionFile)) {
            return null;
        }

        $content = file_get_contents($versionFile);
        if (false === $content) {
            return null;
        }

        $version = trim($content);

        return $version ?: null;
    }

    private function getEditionPath(string $baseDir, string $edition): string
    {
        return $baseDir . DIRECTORY_SEPARATOR . $edition;
    }
}
