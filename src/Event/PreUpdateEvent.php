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
 * Event fired before database updates begin.
 */
class PreUpdateEvent extends GeoIP2UpdateEvent
{
    private bool $skipUpdate = false;

    /**
     * @param string[] $editions
     */
    public function __construct(
        Config $config,
        private array $editions,
        private bool $force = false,
    ) {
        parent::__construct($config, 'geoip2.pre_update');
    }

    /**
     * @return string[]
     */
    public function getEditions(): array
    {
        return $this->editions;
    }

    /**
     * @param string[] $editions
     */
    public function setEditions(array $editions): void
    {
        $this->editions = $editions;
    }

    public function isForce(): bool
    {
        return $this->force;
    }

    public function setForce(bool $force): void
    {
        $this->force = $force;
    }

    public function skipUpdate(): void
    {
        $this->skipUpdate = true;
        $this->stopPropagation();
    }

    public function shouldSkipUpdate(): bool
    {
        return $this->skipUpdate;
    }
}
