<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use danielsreichenbach\GeoIP2Update\GeoIP2UpdatePlugin;
use PHPUnit\Framework\TestCase;

class GeoIP2UpdatePluginTest extends TestCase
{
    private GeoIP2UpdatePlugin $plugin;
    private Composer $composer;
    private IOInterface $io;
    private Config $config;
    private RootPackageInterface $rootPackage;

    protected function setUp(): void
    {
        $this->plugin = new GeoIP2UpdatePlugin();
        $this->composer = $this->createMock(Composer::class);
        $this->io = $this->createMock(IOInterface::class);
        $this->rootPackage = $this->createMock(RootPackageInterface::class);

        // Create a temporary test directory
        $tempDir = sys_get_temp_dir() . '/geoip2-update-test-' . uniqid();
        mkdir($tempDir, 0777, true);
        $this->tempDir = $tempDir;

        // Create a real Config object with baseDir property
        $this->config = new class($tempDir) extends Config {
            private string $baseDir;

            public function __construct(string $baseDir)
            {
                $this->baseDir = $baseDir;
            }

            public function get(string $key, int $flags = 0)
            {
                if ('home' === $key) {
                    return $this->baseDir;
                }

                return null;
            }
        };

        $this->composer->method('getConfig')->willReturn($this->config);
        $this->composer->method('getPackage')->willReturn($this->rootPackage);
    }

    private string $tempDir;

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (isset($this->tempDir) && is_dir($this->tempDir)) {
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

    public function testImplementsRequiredInterfaces(): void
    {
        $this->assertInstanceOf(PluginInterface::class, $this->plugin);
        $this->assertInstanceOf(EventSubscriberInterface::class, $this->plugin);
        $this->assertInstanceOf(Capable::class, $this->plugin);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = GeoIP2UpdatePlugin::getSubscribedEvents();

        $this->assertIsArray($events);
        $this->assertArrayHasKey(ScriptEvents::POST_INSTALL_CMD, $events);
        $this->assertArrayHasKey(ScriptEvents::POST_UPDATE_CMD, $events);
        $this->assertEquals(['onPostInstallCmd', 0], $events[ScriptEvents::POST_INSTALL_CMD]);
        $this->assertEquals(['onPostUpdateCmd', 0], $events[ScriptEvents::POST_UPDATE_CMD]);
    }

    public function testActivate(): void
    {
        // This should not throw any exceptions
        $this->plugin->activate($this->composer, $this->io);
        $this->assertTrue(true);
    }

    public function testDeactivate(): void
    {
        // This should not throw any exceptions
        $this->plugin->deactivate($this->composer, $this->io);
        $this->assertTrue(true);
    }

    public function testUninstall(): void
    {
        // This should not throw any exceptions
        $this->plugin->uninstall($this->composer, $this->io);
        $this->assertTrue(true);
    }

    public function testGetCapabilities(): void
    {
        $capabilities = $this->plugin->getCapabilities();

        $this->assertIsArray($capabilities);
        $this->assertArrayHasKey('Composer\Plugin\Capability\CommandProvider', $capabilities);
        $this->assertEquals('danielsreichenbach\GeoIP2Update\CommandProvider', $capabilities['Composer\Plugin\Capability\CommandProvider']);
    }

    public function testOnPostInstallCmdSkipsInProductionMode(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('isDevMode')->willReturn(false);

        $this->rootPackage->expects($this->never())->method('getExtra');

        $this->plugin->activate($this->composer, $this->io);
        $this->plugin->onPostInstallCmd($event);
    }

    public function testOnPostUpdateCmdSkipsInProductionMode(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('isDevMode')->willReturn(false);

        $this->rootPackage->expects($this->never())->method('getExtra');

        $this->plugin->activate($this->composer, $this->io);
        $this->plugin->onPostUpdateCmd($event);
    }

    public function testOnPostInstallCmdWithNoConfig(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('isDevMode')->willReturn(true);

        $this->rootPackage->method('getExtra')->willReturn([]);

        $this->io->expects($this->never())->method('write');

        $this->plugin->activate($this->composer, $this->io);
        $this->plugin->onPostInstallCmd($event);
    }

    public function testOnPostInstallCmdWithInvalidConfig(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('isDevMode')->willReturn(true);

        $extra = [
            GeoIP2UpdatePlugin::EXTRA_KEY => [
                'maxmind-account-id' => '',
                'maxmind-license-key' => '',
            ],
        ];

        $this->rootPackage->method('getExtra')->willReturn($extra);

        // We expect warning and error outputs
        $writes = [];
        $this->io->expects($this->atLeastOnce())
            ->method('write')
            ->willReturnCallback(function ($message) use (&$writes) {
                $writes[] = $message;
            });

        $this->plugin->activate($this->composer, $this->io);
        $this->plugin->onPostInstallCmd($event);

        // Verify we got warning and error messages
        $hasWarning = false;
        $hasError = false;
        foreach ($writes as $message) {
            if (false !== strpos($message, '<warning>')) {
                $hasWarning = true;
            }
            if (false !== strpos($message, '<error>')) {
                $hasError = true;
            }
        }
        $this->assertTrue($hasWarning, 'Expected warning message');
        $this->assertTrue($hasError, 'Expected error message');
    }

    public function testOnPostInstallCmdWithValidConfig(): void
    {
        $event = $this->createMock(Event::class);
        $event->method('isDevMode')->willReturn(true);

        $extra = [
            GeoIP2UpdatePlugin::EXTRA_KEY => [
                'maxmind-account-id' => '123456',
                'maxmind-license-key' => 'test-license-key',
                'maxmind-database-editions' => ['GeoLite2-Country'],
                'maxmind-database-folder' => $this->tempDir,
            ],
        ];

        $this->rootPackage->method('getExtra')->willReturn($extra);

        $this->io->expects($this->exactly(2))
            ->method('write')
            ->with($this->logicalOr(
                $this->equalTo('<info>Updating GeoIP2 databases...</info>'),
                $this->equalTo('<info>GeoIP2 databases update completed.</info>')
            ));

        $this->plugin->activate($this->composer, $this->io);
        $this->plugin->onPostInstallCmd($event);
    }
}
