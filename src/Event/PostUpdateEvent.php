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
 * Event fired after all databases have been processed.
 */
class PostUpdateEvent extends GeoIP2UpdateEvent
{
    /**
     * @param array<string, array{success: bool, message: string, oldVersion?: string, newVersion?: string}> $results
     */
    public function __construct(
        Config $config,
        private array $results,
    ) {
        parent::__construct($config, 'geoip2.post_update');
    }

    /**
     * @return array<string, array{success: bool, message: string, oldVersion?: string, newVersion?: string}>
     */
    public function getResults(): array
    {
        return $this->results;
    }

    public function hasErrors(): bool
    {
        foreach ($this->results as $result) {
            if (!$result['success']) {
                return true;
            }
        }

        return false;
    }

    public function getSuccessCount(): int
    {
        return count(array_filter($this->results, fn ($result) => $result['success']));
    }

    public function getFailureCount(): int
    {
        return count(array_filter($this->results, fn ($result) => !$result['success']));
    }

    /**
     * @return string[]
     */
    public function getUpdatedEditions(): array
    {
        $updated = [];
        foreach ($this->results as $edition => $result) {
            if ($result['success'] && isset($result['oldVersion'], $result['newVersion'])
                && $result['oldVersion'] !== $result['newVersion']) {
                $updated[] = $edition;
            }
        }

        return $updated;
    }
}
