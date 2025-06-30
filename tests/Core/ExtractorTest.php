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

use danielsreichenbach\GeoIP2Update\Core\Extractor;
use danielsreichenbach\GeoIP2Update\Core\FileManager;
use danielsreichenbach\GeoIP2Update\Exception\ExtractException;
use PHPUnit\Framework\TestCase;

class ExtractorTest extends TestCase
{
    private Extractor $extractor;
    private FileManager $fileManager;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/extractor_test_' . uniqid();
        mkdir($this->tempDir);

        // Use a real FileManager for these tests since extraction requires actual file operations
        $this->fileManager = new FileManager();
        $this->extractor = new Extractor($this->fileManager);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }
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

    public function testExtractValidArchive(): void
    {
        // Create a test tar.gz file
        $testFile = $this->tempDir . '/test.mmdb';
        file_put_contents($testFile, 'test database content');

        $tarPath = $this->tempDir . '/test.tar';
        $archivePath = $this->tempDir . '/test.tar.gz';

        // Create directory structure
        $archiveDir = $this->tempDir . '/GeoLite2-City_20231201';
        mkdir($archiveDir);
        copy($testFile, $archiveDir . '/GeoLite2-City.mmdb');

        // Use tar command to create the archive
        $command = sprintf(
            'cd %s && tar -czf %s GeoLite2-City_20231201/',
            escapeshellarg($this->tempDir),
            escapeshellarg('test.tar.gz')
        );
        exec($command, $output, $returnCode);

        if (0 !== $returnCode) {
            $this->markTestSkipped('Unable to create test archive using tar command');

            return;
        }

        // Clean up
        $this->recursiveRemoveDirectory($archiveDir);

        $destinationPath = $this->tempDir . '/destination';

        $this->extractor->extract($archivePath, $destinationPath);

        // Verify the file was extracted
        $this->assertFileExists($destinationPath . '/GeoLite2-City.mmdb');
        $this->assertEquals('test database content', file_get_contents($destinationPath . '/GeoLite2-City.mmdb'));
    }

    public function testExtractNonExistentArchive(): void
    {
        $archivePath = $this->tempDir . '/nonexistent.tar.gz';
        $destinationPath = $this->tempDir . '/destination';

        $this->expectException(ExtractException::class);
        $this->expectExceptionMessage('Archive file not found');

        $this->extractor->extract($archivePath, $destinationPath);
    }

    public function testExtractInvalidArchive(): void
    {
        $archivePath = $this->tempDir . '/invalid.tar.gz';
        file_put_contents($archivePath, 'invalid archive content');
        $destinationPath = $this->tempDir . '/destination';

        $this->expectException(ExtractException::class);
        $this->expectExceptionMessage('Failed to extract archive');

        $this->extractor->extract($archivePath, $destinationPath);
    }

    public function testExtractEmptyArchive(): void
    {
        $tarPath = $this->tempDir . '/empty.tar';
        $archivePath = $this->tempDir . '/empty.tar.gz';

        // Create empty directory and archive it
        $emptyDir = $this->tempDir . '/empty';
        mkdir($emptyDir);

        // Use tar command to create the archive
        $command = sprintf(
            'cd %s && tar -czf %s empty/',
            escapeshellarg($this->tempDir),
            escapeshellarg('empty.tar.gz')
        );
        exec($command, $output, $returnCode);

        if (0 !== $returnCode) {
            $this->markTestSkipped('Unable to create test archive using tar command');

            return;
        }

        rmdir($emptyDir);

        $destinationPath = $this->tempDir . '/destination';

        // Empty archives should extract without error (no files to copy)
        $this->extractor->extract($archivePath, $destinationPath);

        // Directory should exist but be empty
        $this->assertDirectoryExists($destinationPath);
    }

    public function testExtractArchiveWithoutMmdbFile(): void
    {
        $testFile = $this->tempDir . '/test.txt';
        file_put_contents($testFile, 'test content');

        $tarPath = $this->tempDir . '/no_mmdb.tar';
        $archivePath = $this->tempDir . '/no_mmdb.tar.gz';

        // Use tar command to create the archive
        $command = sprintf(
            'cd %s && tar -czf %s test.txt',
            escapeshellarg($this->tempDir),
            escapeshellarg('no_mmdb.tar.gz')
        );
        exec($command, $output, $returnCode);

        if (0 !== $returnCode) {
            $this->markTestSkipped('Unable to create test archive using tar command');

            return;
        }

        $destinationPath = $this->tempDir . '/destination';

        // Test passes if no exception is thrown since we're just extracting
        // The check for mmdb files was removed from the updated Extractor
        $this->extractor->extract($archivePath, $destinationPath);

        // Verify the text file was extracted
        $this->assertFileExists($destinationPath . '/test.txt');
        $this->assertEquals('test content', file_get_contents($destinationPath . '/test.txt'));
    }
}
