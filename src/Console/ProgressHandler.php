<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Console;

use Composer\IO\IOInterface;

/**
 * Handles progress display for downloads.
 */
class ProgressHandler
{
    private ?IOInterface $io;
    private int $lastProgress = -1;
    private string $currentEdition = '';

    public function __construct(?IOInterface $io = null)
    {
        $this->io = $io;
    }

    public function startDownload(string $edition): void
    {
        $this->currentEdition = $edition;
        $this->lastProgress = -1;

        if (null !== $this->io) {
            $this->io->write(sprintf('  Downloading <info>%s</info>: ', $edition), false);
        }
    }

    public function updateProgress(int $downloadSize, int $downloaded): void
    {
        if (null === $this->io || $downloadSize <= 0) {
            return;
        }

        $progress = (int) round(($downloaded / $downloadSize) * 100);

        // Only update if progress changed
        if ($progress !== $this->lastProgress) {
            $this->lastProgress = $progress;

            // Simple percentage display
            $this->io->overwrite(sprintf(
                '  Downloading <info>%s</info>: <comment>%d%%</comment>',
                $this->currentEdition,
                $progress
            ), false);
        }
    }

    public function completeDownload(): void
    {
        if (null !== $this->io) {
            $this->io->overwrite(sprintf(
                '  Downloading <info>%s</info>: <info>100%% ✓</info>',
                $this->currentEdition
            ));
        }
    }

    public function failDownload(string $error): void
    {
        if (null !== $this->io) {
            $this->io->overwrite(sprintf(
                '  Downloading <info>%s</info>: <error>Failed - %s</error>',
                $this->currentEdition,
                $error
            ));
        }
    }
}
