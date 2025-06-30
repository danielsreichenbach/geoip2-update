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

use Composer\Composer;
use Composer\EventDispatcher\EventDispatcher as ComposerEventDispatcher;
use Composer\IO\IOInterface;

/**
 * Event dispatcher for GeoIP2 update events.
 */
class EventDispatcher
{
    private ?ComposerEventDispatcher $composerDispatcher = null;
    private ?IOInterface $io = null;

    /**
     * @var array<string, array<int, list<callable>>>
     */
    private array $listeners = [];

    public function __construct(
        ?ComposerEventDispatcher $composerDispatcher = null,
        ?IOInterface $io = null,
    ) {
        $this->composerDispatcher = $composerDispatcher;
        $this->io = $io;
    }

    /**
     * Set Composer instance for event dispatching.
     */
    public function setComposer(Composer $composer, IOInterface $io): void
    {
        $this->composerDispatcher = $composer->getEventDispatcher();
        $this->io = $io;
    }

    /**
     * Add an event listener.
     */
    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        if (!isset($this->listeners[$eventName])) {
            $this->listeners[$eventName] = [];
        }

        if (!isset($this->listeners[$eventName][$priority])) {
            $this->listeners[$eventName][$priority] = [];
        }

        $this->listeners[$eventName][$priority][] = $listener;
    }

    /**
     * Dispatch a GeoIP2 update event.
     */
    public function dispatch(GeoIP2UpdateEvent $event): void
    {
        $eventName = $event->getName();

        // Dispatch to local listeners first
        if (isset($this->listeners[$eventName])) {
            // Sort by priority (higher priority first)
            krsort($this->listeners[$eventName]);

            foreach ($this->listeners[$eventName] as $priority => $listeners) {
                foreach ($listeners as $listener) {
                    try {
                        $listener($event);
                    } catch (\Exception $e) {
                        if (null !== $this->io) {
                            $this->io->writeError(sprintf(
                                '<error>Error in event listener for %s: %s</error>',
                                $eventName,
                                $e->getMessage()
                            ));
                        }
                    }

                    if ($event->isPropagationStopped()) {
                        return;
                    }
                }
            }
        }

        // Then dispatch through Composer's event system
        if (null !== $this->composerDispatcher) {
            $this->composerDispatcher->dispatch($eventName, $event);
        }

        // Log event if IO is available
        if (null !== $this->io && $this->io->isVerbose()) {
            $this->logEvent($event);
        }
    }

    /**
     * Log event details for debugging.
     */
    private function logEvent(GeoIP2UpdateEvent $event): void
    {
        if (null === $this->io) {
            return;
        }

        switch (true) {
            case $event instanceof PreUpdateEvent:
                $this->io->write(sprintf(
                    '<comment>Event: %s (editions: %s, force: %s)</comment>',
                    $event->getName(),
                    implode(', ', $event->getEditions()),
                    $event->isForce() ? 'yes' : 'no'
                ), true, IOInterface::VERBOSE);
                break;

            case $event instanceof DatabaseUpdateEvent:
                $this->io->write(sprintf(
                    '<comment>Event: %s (edition: %s, current: %s, remote: %s)</comment>',
                    $event->getName(),
                    $event->getEdition(),
                    $event->getCurrentVersion() ?: 'none',
                    $event->getRemoteVersion() ?: 'unknown'
                ), true, IOInterface::VERBOSE);
                break;

            case $event instanceof UpdateErrorEvent:
                $this->io->write(sprintf(
                    '<comment>Event: %s (edition: %s, error: %s)</comment>',
                    $event->getName(),
                    $event->getEdition(),
                    $event->getException()->getMessage()
                ), true, IOInterface::VERBOSE);
                break;

            case $event instanceof PostUpdateEvent:
                $this->io->write(sprintf(
                    '<comment>Event: %s (success: %d, failed: %d)</comment>',
                    $event->getName(),
                    $event->getSuccessCount(),
                    $event->getFailureCount()
                ), true, IOInterface::VERBOSE);
                break;

            default:
                $this->io->write(sprintf(
                    '<comment>Event: %s</comment>',
                    $event->getName()
                ), true, IOInterface::VERBOSE);
        }
    }

    /**
     * Check if event propagation was stopped.
     */
    public function isPropagationStopped(GeoIP2UpdateEvent $event): bool
    {
        return $event->isPropagationStopped();
    }
}
