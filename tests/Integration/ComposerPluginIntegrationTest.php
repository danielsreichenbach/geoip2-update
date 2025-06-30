<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Tests\Integration;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventDispatcher;
use Composer\IO\BufferIO;
use Composer\Package\RootPackage;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use danielsreichenbach\GeoIP2Update\GeoIP2UpdatePlugin;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the GeoIP2 Update Composer plugin.
 *
 * These tests verify that the plugin integrates correctly with Composer
 * and responds to Composer events as expected.
 */
class ComposerPluginIntegrationTest extends TestCase
{
    private string $tempDir;
    private Composer $composer;
    private BufferIO $io;
    private GeoIP2UpdatePlugin $plugin;

    protected function setUp(): void
    {
        // Create temporary directory
        $this->tempDir = sys_get_temp_dir() . '/geoip2-plugin-integration-' . uniqid();
        mkdir($this->tempDir, 0777, true);

        // Create test composer.json
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'name' => 'test/test',
            'type' => 'project',
            'require' => [
                'danielsreichenbach/geoip2-update' => '*',
            ],
        ]));

        // Set up Composer instance
        $this->composer = new Composer();
        $config = new Config(false, $this->tempDir);
        $config->merge(['config' => ['home' => $this->tempDir]]);
        $this->composer->setConfig($config);

        // Set up IO
        $this->io = new BufferIO();

        // Set up event dispatcher
        $eventDispatcher = new EventDispatcher($this->composer, $this->io);
        $this->composer->setEventDispatcher($eventDispatcher);

        // Create plugin instance
        $this->plugin = new GeoIP2UpdatePlugin();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->recursiveRemoveDirectory($this->tempDir);
        }
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testPluginActivation(): void
    {
        // Activate plugin
        $this->plugin->activate($this->composer, $this->io);

        // Verify plugin is registered
        $subscribedEvents = GeoIP2UpdatePlugin::getSubscribedEvents();
        $this->assertArrayHasKey(ScriptEvents::POST_INSTALL_CMD, $subscribedEvents);
        $this->assertArrayHasKey(ScriptEvents::POST_UPDATE_CMD, $subscribedEvents);
    }

    public function testPostInstallEventInDevMode(): void
    {
        // Set up package with valid configuration
        $rootPackage = new RootPackage('test/test', '1.0.0', '1.0.0');
        $rootPackage->setExtra([
            GeoIP2UpdatePlugin::EXTRA_KEY => [
                'maxmind-account-id' => '123456',
                'maxmind-license-key' => 'test-license-key',
                'maxmind-database-editions' => ['GeoLite2-Country'],
                'maxmind-database-folder' => $this->tempDir . '/databases',
            ],
        ]);
        $this->composer->setPackage($rootPackage);

        // Activate plugin
        $this->plugin->activate($this->composer, $this->io);

        // Create event in dev mode
        $event = $this->createMock(Event::class);
        $event->method('isDevMode')->willReturn(true);
        $event->method('getComposer')->willReturn($this->composer);
        $event->method('getIO')->willReturn($this->io);

        // Trigger post-install event
        $this->plugin->onPostInstallCmd($event);

        // Verify output
        $output = $this->io->getOutput();
        $this->assertStringContainsString('Updating GeoIP2 databases', $output);
    }

    public function testPostUpdateEventInProductionMode(): void
    {
        // Set up package with configuration
        $rootPackage = new RootPackage('test/test', '1.0.0', '1.0.0');
        $rootPackage->setExtra([
            GeoIP2UpdatePlugin::EXTRA_KEY => [
                'maxmind-account-id' => '123456',
                'maxmind-license-key' => 'test-license-key',
            ],
        ]);
        $this->composer->setPackage($rootPackage);

        // Activate plugin
        $this->plugin->activate($this->composer, $this->io);

        // Create event in production mode
        $event = $this->createMock(Event::class);
        $event->method('isDevMode')->willReturn(false);
        $event->expects($this->never())->method('getComposer');

        // Trigger post-update event
        $this->plugin->onPostUpdateCmd($event);

        // Verify no output (skipped in production)
        $output = $this->io->getOutput();
        $this->assertEmpty($output);
    }

    public function testPluginWithMissingConfiguration(): void
    {
        // Set up package without configuration
        $rootPackage = new RootPackage('test/test', '1.0.0', '1.0.0');
        $rootPackage->setExtra([]);
        $this->composer->setPackage($rootPackage);

        // Activate plugin
        $this->plugin->activate($this->composer, $this->io);

        // Create event
        $event = $this->createMock(Event::class);
        $event->method('isDevMode')->willReturn(true);
        $event->method('getComposer')->willReturn($this->composer);
        $event->method('getIO')->willReturn($this->io);

        // Trigger event
        $this->plugin->onPostInstallCmd($event);

        // Verify no errors (plugin should handle gracefully)
        $output = $this->io->getOutput();
        $this->assertStringNotContainsString('error', strtolower($output));
    }

    public function testPluginWithInvalidConfiguration(): void
    {
        // Set up package with invalid configuration
        $rootPackage = new RootPackage('test/test', '1.0.0', '1.0.0');
        $rootPackage->setExtra([
            GeoIP2UpdatePlugin::EXTRA_KEY => [
                'maxmind-account-id' => '',
                'maxmind-license-key' => '',
            ],
        ]);
        $this->composer->setPackage($rootPackage);

        // Activate plugin
        $this->plugin->activate($this->composer, $this->io);

        // Create event
        $event = $this->createMock(Event::class);
        $event->method('isDevMode')->willReturn(true);
        $event->method('getComposer')->willReturn($this->composer);
        $event->method('getIO')->willReturn($this->io);

        // Trigger event
        $this->plugin->onPostInstallCmd($event);

        // Verify warning output
        $output = $this->io->getOutput();
        $this->assertStringContainsString('warning', strtolower($output));
        $this->assertStringContainsString('configuration', strtolower($output));
    }

    public function testCommandProviderCapability(): void
    {
        $capabilities = $this->plugin->getCapabilities();

        $this->assertArrayHasKey('Composer\Plugin\Capability\CommandProvider', $capabilities);
        $this->assertEquals(
            'danielsreichenbach\GeoIP2Update\CommandProvider',
            $capabilities['Composer\Plugin\Capability\CommandProvider']
        );

        // Verify command provider can be instantiated
        $providerClass = $capabilities['Composer\Plugin\Capability\CommandProvider'];
        $provider = new $providerClass();

        $commands = $provider->getCommands();
        $this->assertIsArray($commands);
        $this->assertNotEmpty($commands);

        // Verify geoip2:update command exists
        $commandNames = array_map(function ($command) {
            return $command->getName();
        }, $commands);
        $this->assertContains('geoip2:update', $commandNames);
    }

    public function testPluginDeactivation(): void
    {
        // Activate then deactivate
        $this->plugin->activate($this->composer, $this->io);
        $this->plugin->deactivate($this->composer, $this->io);

        // Plugin should still respond to events (Composer handles unsubscription)
        $this->assertTrue(true);
    }

    public function testPluginUninstall(): void
    {
        // Activate then uninstall
        $this->plugin->activate($this->composer, $this->io);
        $this->plugin->uninstall($this->composer, $this->io);

        // Plugin should handle gracefully
        $this->assertTrue(true);
    }
}
