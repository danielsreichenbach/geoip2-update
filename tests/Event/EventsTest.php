<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Tests\Event;

use danielsreichenbach\GeoIP2Update\Event\DatabaseUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\PostUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\PreUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\UpdateErrorEvent;
use danielsreichenbach\GeoIP2Update\Model\Config;
use PHPUnit\Framework\TestCase;

class EventsTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
    }

    public function testPreUpdateEvent(): void
    {
        $editions = ['GeoLite2-City', 'GeoLite2-Country'];
        $event = new PreUpdateEvent($this->config, $editions, false);

        $this->assertSame($this->config, $event->getConfig());
        $this->assertEquals($editions, $event->getEditions());
        $this->assertFalse($event->isForce());
        $this->assertFalse($event->shouldSkipUpdate());
        $this->assertEquals('geoip2.pre_update', $event->getName());

        // Test modification
        $event->setEditions(['GeoLite2-ASN']);
        $this->assertEquals(['GeoLite2-ASN'], $event->getEditions());

        $event->setForce(true);
        $this->assertTrue($event->isForce());

        $event->skipUpdate();
        $this->assertTrue($event->shouldSkipUpdate());
    }

    public function testDatabaseUpdateEvent(): void
    {
        $event = new DatabaseUpdateEvent(
            $this->config,
            'GeoLite2-City',
            '2023-11-01',
            '2023-12-01'
        );

        $this->assertSame($this->config, $event->getConfig());
        $this->assertEquals('GeoLite2-City', $event->getEdition());
        $this->assertEquals('2023-11-01', $event->getCurrentVersion());
        $this->assertEquals('2023-12-01', $event->getRemoteVersion());
        $this->assertFalse($event->shouldSkipDatabase());
        $this->assertEquals('geoip2.database_update', $event->getName());

        $event->skipDatabase();
        $this->assertTrue($event->shouldSkipDatabase());
    }

    public function testDatabaseUpdateEventWithNullVersion(): void
    {
        $event = new DatabaseUpdateEvent(
            $this->config,
            'GeoLite2-City',
            null,
            '2023-12-01'
        );

        $this->assertNull($event->getCurrentVersion());
    }

    public function testPostUpdateEvent(): void
    {
        $results = [
            'GeoLite2-City' => [
                'success' => true,
                'message' => 'Updated',
                'oldVersion' => '2023-11-01',
                'newVersion' => '2023-12-01',
            ],
            'GeoLite2-Country' => [
                'success' => false,
                'message' => 'Failed',
            ],
        ];

        $event = new PostUpdateEvent($this->config, $results);

        $this->assertSame($this->config, $event->getConfig());
        $this->assertEquals($results, $event->getResults());
        $this->assertTrue($event->hasErrors());
        $this->assertEquals(1, $event->getSuccessCount());
        $this->assertEquals(1, $event->getFailureCount());
        $this->assertEquals(['GeoLite2-City'], $event->getUpdatedEditions());
        $this->assertEquals('geoip2.post_update', $event->getName());
    }

    public function testPostUpdateEventWithoutErrors(): void
    {
        $results = [
            'GeoLite2-City' => [
                'success' => true,
                'message' => 'Up to date',
                'oldVersion' => '2023-12-01',
                'newVersion' => '2023-12-01',
            ],
        ];

        $event = new PostUpdateEvent($this->config, $results);

        $this->assertFalse($event->hasErrors());
        $this->assertEquals(1, $event->getSuccessCount());
        $this->assertEquals(0, $event->getFailureCount());
        $this->assertEmpty($event->getUpdatedEditions());
    }

    public function testUpdateErrorEvent(): void
    {
        $exception = new \RuntimeException('Test error');
        $event = new UpdateErrorEvent($this->config, 'GeoLite2-City', $exception);

        $this->assertSame($this->config, $event->getConfig());
        $this->assertEquals('GeoLite2-City', $event->getEdition());
        $this->assertSame($exception, $event->getException());
        $this->assertTrue($event->shouldContinue());
        $this->assertFalse($event->shouldRetry());
        $this->assertEquals('geoip2.update_error', $event->getName());

        $event->retry();
        $this->assertTrue($event->shouldRetry());

        $event->stopProcessing();
        $this->assertFalse($event->shouldContinue());
    }
}
