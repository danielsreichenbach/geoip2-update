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

use danielsreichenbach\GeoIP2Update\Exception\FileException;

class FileManager
{
    /**
     * Ensure directory exists and is writable.
     */
    public function ensureDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            if (!mkdir($path, 0755, true)) {
                throw new FileException(sprintf('Failed to create directory: %s', $path));
            }
        }

        if (!is_writable($path)) {
            throw new FileException(sprintf('Directory is not writable: %s', $path));
        }
    }

    /**
     * Write content to file.
     */
    public function writeFile(string $path, string $content): void
    {
        $directory = dirname($path);
        $this->ensureDirectoryExists($directory);

        if (false === file_put_contents($path, $content)) {
            throw new FileException(sprintf('Failed to write file: %s', $path));
        }
    }

    /**
     * Delete file if exists.
     */
    public function deleteFile(string $path): void
    {
        if (file_exists($path) && !unlink($path)) {
            throw new FileException(sprintf('Failed to delete file: %s', $path));
        }
    }

    /**
     * Delete directory recursively.
     */
    public function deleteDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }

    /**
     * Get temporary file path.
     */
    public function getTempFile(string $filename): string
    {
        $tempDir = sys_get_temp_dir();

        return $tempDir . DIRECTORY_SEPARATOR . 'geoip2_' . uniqid() . '_' . $filename;
    }

    /**
     * Get temporary directory path.
     */
    public function getTempDir(): string
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'geoip2_' . uniqid();
        $this->ensureDirectoryExists($tempDir);

        return $tempDir;
    }

    /**
     * Validate directory is writable.
     *
     * @return string[]
     */
    public function validateDirectory(string $path): array
    {
        $errors = [];

        if (empty($path)) {
            $errors[] = 'Directory path is empty';
        } elseif (!file_exists($path)) {
            $errors[] = sprintf('Directory does not exist: %s', $path);
        } elseif (!is_dir($path)) {
            $errors[] = sprintf('Path is not a directory: %s', $path);
        } elseif (!is_writable($path)) {
            $errors[] = sprintf('Directory is not writable: %s', $path);
        }

        return $errors;
    }
}
