<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Event;

use danielsreichenbach\GeoIP2Update\Model\Config;

/**
 * Event fired for each database being updated.
 */
class DatabaseUpdateEvent extends GeoIP2UpdateEvent
{
    private bool $skipDatabase = false;

    public function __construct(
        Config $config,
        private string $edition,
        private ?string $currentVersion = null,
        private ?string $remoteVersion = null,
    ) {
        parent::__construct($config, 'geoip2.database_update');
    }

    public function getEdition(): string
    {
        return $this->edition;
    }

    public function getCurrentVersion(): ?string
    {
        return $this->currentVersion;
    }

    public function getRemoteVersion(): ?string
    {
        return $this->remoteVersion;
    }

    public function skipDatabase(): void
    {
        $this->skipDatabase = true;
    }

    public function shouldSkipDatabase(): bool
    {
        return $this->skipDatabase;
    }

    public function isUpdateRequired(): bool
    {
        if (null === $this->currentVersion || null === $this->remoteVersion) {
            return true;
        }

        return $this->currentVersion < $this->remoteVersion;
    }
}
