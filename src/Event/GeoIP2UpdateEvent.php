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

use Composer\EventDispatcher\Event;
use danielsreichenbach\GeoIP2Update\Model\Config;

/**
 * Base event class for GeoIP2 update events.
 */
class GeoIP2UpdateEvent extends Event
{
    private bool $stopPropagation = false;

    public function __construct(
        private Config $config,
        string $name = '',
    ) {
        parent::__construct($name);
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function stopPropagation(): void
    {
        $this->stopPropagation = true;
        parent::stopPropagation();
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopPropagation || parent::isPropagationStopped();
    }
}
