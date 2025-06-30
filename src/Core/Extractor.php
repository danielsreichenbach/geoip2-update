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

use danielsreichenbach\GeoIP2Update\Exception\ExtractException;

class Extractor
{
    public function __construct(
        private FileManager $fileManager,
    ) {
    }

    /**
     * Extract tar.gz archive to destination.
     */
    public function extract(string $archivePath, string $destination): void
    {
        if (!file_exists($archivePath)) {
            throw new ExtractException(sprintf('Archive file not found: %s', $archivePath));
        }

        // Ensure destination exists
        $this->fileManager->ensureDirectoryExists($destination);

        try {
            $phar = new \PharData($archivePath);

            // Get the root directory name from the archive
            $rootDir = null;
            foreach ($phar as $file) {
                $parts = explode('/', $file->getFilename());
                if (!$rootDir && \count($parts) > 0) { // @phpstan-ignore-line
                    $rootDir = $parts[0];
                }
            }

            // Extract to temp directory first
            $tempDir = $this->fileManager->getTempDir();
            $phar->extractTo($tempDir);

            // Move files from extracted root directory to destination
            if ($rootDir && is_dir($tempDir . DIRECTORY_SEPARATOR . $rootDir)) {
                $this->moveFiles(
                    $tempDir . DIRECTORY_SEPARATOR . $rootDir,
                    $destination
                );

                // Clean up temp directory
                $this->fileManager->deleteDirectory($tempDir);
            } else {
                // Archive doesn't have a root directory, move all files
                $this->moveFiles($tempDir, $destination);
                $this->fileManager->deleteDirectory($tempDir);
            }
        } catch (\Exception $e) {
            throw new ExtractException(
                sprintf('Failed to extract archive: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    /**
     * Move files from source to destination.
     */
    private function moveFiles(string $source, string $destination): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $targetPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                $this->fileManager->ensureDirectoryExists($targetPath);
            } else {
                // Ensure parent directory exists
                $this->fileManager->ensureDirectoryExists(dirname($targetPath));

                // Copy file
                if (!copy($item->getPathname(), $targetPath)) {
                    throw new ExtractException(
                        sprintf('Failed to copy file: %s to %s', $item->getPathname(), $targetPath)
                    );
                }
            }
        }
    }
}
