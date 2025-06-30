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
 * Event fired when an error occurs during update.
 */
class UpdateErrorEvent extends GeoIP2UpdateEvent
{
    private bool $shouldRetry = false;
    private bool $shouldContinue = true;

    public function __construct(
        Config $config,
        private string $edition,
        private \Throwable $error,
    ) {
        parent::__construct($config, 'geoip2.update_error');
    }

    public function getEdition(): string
    {
        return $this->edition;
    }

    public function getError(): \Throwable
    {
        return $this->error;
    }

    public function getException(): \Throwable
    {
        return $this->error;
    }

    public function retry(): void
    {
        $this->shouldRetry = true;
    }

    public function shouldRetry(): bool
    {
        return $this->shouldRetry;
    }

    public function stopUpdates(): void
    {
        $this->shouldContinue = false;
        $this->stopPropagation();
    }

    public function shouldContinue(): bool
    {
        return $this->shouldContinue;
    }

    /**
     * Alias for stopUpdates() for backward compatibility.
     */
    public function stopProcessing(): void
    {
        $this->stopUpdates();
    }
}
