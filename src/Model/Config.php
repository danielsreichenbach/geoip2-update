<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Model;

class Config
{
    public function __construct(
        private string $maxmindAccountId,
        private string $maxmindLicenseKey,
        /** @var string[] */
        private array $maxmindDatabaseEditions,
        private string $maxmindDatabaseFolder,
    ) {
    }

    public function getMaxmindAccountId(): string
    {
        return $this->maxmindAccountId;
    }

    public function getMaxmindLicenseKey(): string
    {
        return $this->maxmindLicenseKey;
    }

    /**
     * @return string[]
     */
    public function getMaxmindDatabaseEditions(): array
    {
        return $this->maxmindDatabaseEditions;
    }

    public function getMaxmindDatabaseFolder(): string
    {
        return $this->maxmindDatabaseFolder;
    }

    public function getAccountId(): string
    {
        return $this->maxmindAccountId;
    }

    public function getLicenseKey(): string
    {
        return $this->maxmindLicenseKey;
    }

    /**
     * @return string[]
     */
    public function getEditions(): array
    {
        return $this->maxmindDatabaseEditions;
    }

    public function getDatabaseFolder(): string
    {
        return $this->maxmindDatabaseFolder;
    }

    public function isValid(): bool
    {
        return !empty($this->maxmindAccountId)
            && !empty($this->maxmindLicenseKey)
            && !empty($this->maxmindDatabaseEditions);
    }
}
