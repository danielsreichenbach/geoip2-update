<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Tests\Console;

use Composer\IO\IOInterface;
use danielsreichenbach\GeoIP2Update\Console\ProgressHandler;
use PHPUnit\Framework\TestCase;

class ProgressHandlerTest extends TestCase
{
    private ProgressHandler $handler;
    private IOInterface $io;

    protected function setUp(): void
    {
        $this->io = $this->createMock(IOInterface::class);
        $this->handler = new ProgressHandler($this->io);
    }

    public function testStartDownload(): void
    {
        $this->io->expects($this->once())
            ->method('write')
            ->with('  Downloading <info>GeoLite2-City</info>: ', false);

        $this->handler->startDownload('GeoLite2-City');
    }

    public function testUpdateProgress(): void
    {
        $this->handler->startDownload('GeoLite2-City');

        $this->io->expects($this->exactly(3))
            ->method('overwrite')
            ->withConsecutive(
                ['  Downloading <info>GeoLite2-City</info>: <comment>25%</comment>', false],
                ['  Downloading <info>GeoLite2-City</info>: <comment>50%</comment>', false],
                ['  Downloading <info>GeoLite2-City</info>: <comment>75%</comment>', false]
            );

        $this->handler->updateProgress(100, 25);
        $this->handler->updateProgress(100, 50);
        $this->handler->updateProgress(100, 75);

        // Same progress should not trigger another update
        $this->handler->updateProgress(100, 75);
    }

    public function testUpdateProgressWithZeroSize(): void
    {
        $this->handler->startDownload('GeoLite2-City');

        $this->io->expects($this->never())
            ->method('overwrite');

        $this->handler->updateProgress(0, 0);
    }

    public function testCompleteDownload(): void
    {
        $this->io->expects($this->once())
            ->method('overwrite')
            ->with('  Downloading <info>GeoLite2-City</info>: <info>100% ✓</info>');

        $this->handler->startDownload('GeoLite2-City');
        $this->handler->completeDownload();
    }

    public function testFailDownload(): void
    {
        $this->io->expects($this->once())
            ->method('overwrite')
            ->with('  Downloading <info>GeoLite2-City</info>: <error>Failed - Connection timeout</error>');

        $this->handler->startDownload('GeoLite2-City');
        $this->handler->failDownload('Connection timeout');
    }

    public function testWithoutIOInterface(): void
    {
        $handler = new ProgressHandler(null);

        // Should not throw exceptions
        $handler->startDownload('GeoLite2-City');
        $handler->updateProgress(100, 50);
        $handler->completeDownload();
        $handler->failDownload('Error');

        $this->assertTrue(true);
    }

    public function testProgressRounding(): void
    {
        $this->handler->startDownload('GeoLite2-City');

        $this->io->expects($this->once())
            ->method('overwrite')
            ->with('  Downloading <info>GeoLite2-City</info>: <comment>33%</comment>', false);

        // 1/3 should round to 33%
        $this->handler->updateProgress(3, 1);
    }
}
