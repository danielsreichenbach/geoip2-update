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

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher as ComposerEventDispatcher;
use Composer\IO\IOInterface;
use danielsreichenbach\GeoIP2Update\Event\EventDispatcher;
use danielsreichenbach\GeoIP2Update\Event\GeoIP2UpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\PreUpdateEvent;
use danielsreichenbach\GeoIP2Update\Model\Config;
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;
    private IOInterface $io;

    protected function setUp(): void
    {
        $this->io = $this->createMock(IOInterface::class);
        $this->dispatcher = new EventDispatcher();
    }

    public function testSetComposer(): void
    {
        $composer = $this->createMock(Composer::class);
        $composerDispatcher = $this->createMock(ComposerEventDispatcher::class);

        $composer->expects($this->once())
            ->method('getEventDispatcher')
            ->willReturn($composerDispatcher);

        $this->dispatcher->setComposer($composer, $this->io);

        // Test that composer events are dispatched
        $event = new GeoIP2UpdateEvent(
            $this->createMock(Config::class),
            'test.event'
        );

        $composerDispatcher->expects($this->once())
            ->method('dispatch')
            ->with('test.event', $event);

        $this->dispatcher->dispatch($event);
    }

    public function testAddAndDispatchListener(): void
    {
        $listenerCalled = false;
        $receivedEvent = null;

        $listener = function (GeoIP2UpdateEvent $event) use (&$listenerCalled, &$receivedEvent) {
            $listenerCalled = true;
            $receivedEvent = $event;
        };

        $this->dispatcher->addListener('test.event', $listener);

        $event = new GeoIP2UpdateEvent(
            $this->createMock(Config::class),
            'test.event'
        );

        $this->dispatcher->dispatch($event);

        $this->assertTrue($listenerCalled);
        $this->assertSame($event, $receivedEvent);
    }

    public function testMultipleListeners(): void
    {
        $callOrder = [];

        $listener1 = function () use (&$callOrder) {
            $callOrder[] = 'listener1';
        };

        $listener2 = function () use (&$callOrder) {
            $callOrder[] = 'listener2';
        };

        $listener3 = function () use (&$callOrder) {
            $callOrder[] = 'listener3';
        };

        // Add listeners with different priorities
        $this->dispatcher->addListener('test.event', $listener1, 10);
        $this->dispatcher->addListener('test.event', $listener2, 0);
        $this->dispatcher->addListener('test.event', $listener3, 20);

        $event = new GeoIP2UpdateEvent(
            $this->createMock(Config::class),
            'test.event'
        );

        $this->dispatcher->dispatch($event);

        // Higher priority listeners should be called first
        $this->assertEquals(['listener3', 'listener1', 'listener2'], $callOrder);
    }

    public function testListenerCanModifyEvent(): void
    {
        $config = $this->createMock(Config::class);
        $config->method('getEditions')->willReturn(['GeoLite2-City']);

        $listener = function (PreUpdateEvent $event) {
            $event->setEditions(['GeoLite2-Country']);
        };

        $this->dispatcher->addListener('geoip2.pre_update', $listener);

        $event = new PreUpdateEvent($config, ['GeoLite2-City'], false);

        $this->dispatcher->dispatch($event);

        $this->assertEquals(['GeoLite2-Country'], $event->getEditions());
    }

    public function testDispatchWithoutListeners(): void
    {
        $event = new GeoIP2UpdateEvent(
            $this->createMock(Config::class),
            'test.event'
        );

        // Should not throw exception
        $this->dispatcher->dispatch($event);

        $this->assertTrue(true);
    }

    public function testDispatchToComposerAndLocalListeners(): void
    {
        // Set up composer dispatcher
        $composer = $this->createMock(Composer::class);
        $composerDispatcher = $this->createMock(ComposerEventDispatcher::class);

        $composer->method('getEventDispatcher')
            ->willReturn($composerDispatcher);

        $this->dispatcher->setComposer($composer, $this->io);

        // Add local listener
        $localListenerCalled = false;
        $localListener = function () use (&$localListenerCalled) {
            $localListenerCalled = true;
        };

        $this->dispatcher->addListener('test.event', $localListener);

        // Expect composer dispatch
        $composerDispatcher->expects($this->once())
            ->method('dispatch')
            ->with('test.event');

        $event = new GeoIP2UpdateEvent(
            $this->createMock(Config::class),
            'test.event'
        );

        $this->dispatcher->dispatch($event);

        $this->assertTrue($localListenerCalled);
    }

    public function testListenerExceptionHandling(): void
    {
        $listener1Called = false;
        $listener2Called = false;

        $listener1 = function () use (&$listener1Called) {
            $listener1Called = true;
            throw new \RuntimeException('Test exception');
        };

        $listener2 = function () use (&$listener2Called) {
            $listener2Called = true;
        };

        $this->dispatcher->addListener('test.event', $listener1);
        $this->dispatcher->addListener('test.event', $listener2);

        // Expect error to be written
        $this->io->expects($this->once())
            ->method('writeError')
            ->with($this->stringContains('Error in event listener'));

        $this->dispatcher->setComposer($this->createMock(Composer::class), $this->io);

        $event = new GeoIP2UpdateEvent(
            $this->createMock(Config::class),
            'test.event'
        );

        $this->dispatcher->dispatch($event);

        // Both listeners should be called despite exception
        $this->assertTrue($listener1Called);
        $this->assertTrue($listener2Called);
    }
}
