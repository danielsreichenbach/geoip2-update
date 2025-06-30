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

use danielsreichenbach\GeoIP2Update\Core\FileManager;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;

class FileManagerTest extends TestCase
{
    private FileManager $fileManager;
    private string $root;

    protected function setUp(): void
    {
        $this->fileManager = new FileManager();
        $this->root = vfsStream::setup('root')->url();
    }

    public function testValidateDirectoryWithValidDirectory(): void
    {
        $dir = $this->root . '/valid';
        mkdir($dir);

        $errors = $this->fileManager->validateDirectory($dir);

        $this->assertEmpty($errors);
    }

    public function testValidateDirectoryWithNonExistentDirectory(): void
    {
        $dir = $this->root . '/nonexistent';

        $errors = $this->fileManager->validateDirectory($dir);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('does not exist', $errors[0]);
    }

    public function testValidateDirectoryWithFile(): void
    {
        $file = $this->root . '/file.txt';
        file_put_contents($file, 'content');

        $errors = $this->fileManager->validateDirectory($file);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('not a directory', $errors[0]);
    }

    public function testValidateDirectoryWithNonWritableDirectory(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Directory permissions test skipped on Windows');
        }

        $dir = $this->root . '/readonly';
        mkdir($dir);
        chmod($dir, 0444);

        $errors = $this->fileManager->validateDirectory($dir);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('not writable', $errors[0]);

        // Cleanup
        chmod($dir, 0755);
    }

    public function testGetTempFile(): void
    {
        $tempFile = $this->fileManager->getTempFile('test.txt');

        $this->assertStringContainsString('test.txt', $tempFile);
        $this->assertStringContainsString(sys_get_temp_dir(), $tempFile);
    }

    public function testWriteFile(): void
    {
        $file = $this->root . '/test.txt';
        $content = 'test content';

        $this->fileManager->writeFile($file, $content);

        $this->assertFileExists($file);
        $this->assertEquals($content, file_get_contents($file));
    }

    public function testDeleteFile(): void
    {
        $file = $this->root . '/test.txt';
        file_put_contents($file, 'content');

        $this->fileManager->deleteFile($file);

        $this->assertFileDoesNotExist($file);
    }

    public function testDeleteFileHandlesNonExistentFile(): void
    {
        $file = $this->root . '/nonexistent.txt';

        // Should not throw exception
        $this->fileManager->deleteFile($file);

        $this->assertFileDoesNotExist($file);
    }

    public function testEnsureDirectoryExists(): void
    {
        $dir = $this->root . '/new/nested/directory';

        $this->fileManager->ensureDirectoryExists($dir);

        $this->assertDirectoryExists($dir);
    }

    public function testEnsureDirectoryExistsWithExistingDirectory(): void
    {
        $dir = $this->root . '/existing';
        mkdir($dir);

        // Should not throw exception
        $this->fileManager->ensureDirectoryExists($dir);

        $this->assertDirectoryExists($dir);
    }
}
