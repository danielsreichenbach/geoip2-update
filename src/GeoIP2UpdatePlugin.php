<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use danielsreichenbach\GeoIP2Update\Config\ConfigBuilder;
use danielsreichenbach\GeoIP2Update\Config\ConfigLocator;

class GeoIP2UpdatePlugin implements PluginInterface, EventSubscriberInterface, Capable
{
    public const EXTRA_KEY = 'geoip2-update';

    private Composer $composer;
    private IOInterface $io;
    private ConfigBuilder $configBuilder;
    private ConfigLocator $configLocator;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->configBuilder = new ConfigBuilder();
        $this->configLocator = new ConfigLocator($this->configBuilder);
    }

    public function deactivate(Composer $composer, IOInterface $io)
    {
    }

    public function uninstall(Composer $composer, IOInterface $io)
    {
    }

    public function getCapabilities()
    {
        return [
            CommandProviderCapability::class => CommandProvider::class,
        ];
    }

    public static function getSubscribedEvents()
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => ['onPostInstallCmd', 0],
            ScriptEvents::POST_UPDATE_CMD => ['onPostUpdateCmd', 0],
        ];
    }

    public function onPostInstallCmd(Event $event): void
    {
        $this->updateDatabases($event);
    }

    public function onPostUpdateCmd(Event $event): void
    {
        $this->updateDatabases($event);
    }

    private function updateDatabases(Event $event): void
    {
        if (!$event->isDevMode()) {
            return;
        }

        $config = $this->configLocator->locate($this->composer);

        if (null === $config) {
            return;
        }

        $warnings = $this->configBuilder->getWarnings();
        if (count($warnings) > 0) {
            $this->io->write('<warning>GeoIP2 Update configuration warnings:</warning>');
            foreach ($warnings as $warning) {
                $this->io->write(sprintf('  - %s', $warning));
            }
        }

        if (!$config->isValid()) {
            $this->io->write('<error>GeoIP2 Update configuration is invalid. Skipping database update.</error>');

            return;
        }

        $this->io->write('<info>Updating GeoIP2 databases...</info>');

        // Validate database directory
        $fileManager = Factory::createFileManager();
        $errors = $fileManager->validateDirectory($config->getDatabaseFolder());

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $this->io->writeError(sprintf('<error>%s</error>', $error));
            }

            return;
        }

        // Create event dispatcher
        $eventDispatcher = Factory::createEventDispatcher($this->composer, $this->io);

        // Update databases with progress display
        $updater = Factory::createDatabaseUpdater($eventDispatcher, $this->io);
        $results = $updater->update($config);

        // Display results
        foreach ($results as $edition => $result) {
            if ($result['success']) {
                $this->io->write(sprintf(
                    '<info>✓ %s</info>',
                    $result['message']
                ));
            } else {
                $this->io->writeError(sprintf(
                    '<error>✗ %s</error>',
                    $result['message']
                ));
            }
        }

        $this->io->write('<info>GeoIP2 databases update completed.</info>');
    }
}
