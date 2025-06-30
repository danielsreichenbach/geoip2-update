<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Tests\Config;

use Composer\Composer;
use Composer\Config;
use Composer\Package\RootPackage;
use danielsreichenbach\GeoIP2Update\Config\ConfigBuilder;
use danielsreichenbach\GeoIP2Update\Config\ConfigLocator;
use danielsreichenbach\GeoIP2Update\GeoIP2UpdatePlugin;
use PHPUnit\Framework\TestCase;

class ConfigLocatorTest extends TestCase
{
    /** @var string */
    private $localConfigPath;

    /** @var string */
    private $globalConfigPath;

    /** @var Config */
    private $config;

    /** @var Composer */
    private $composer;

    /** @var ConfigLocator */
    private $SUT;

    /** @var ConfigBuilder */
    private $configBuilder;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->localConfigPath = realpath(__DIR__ . '/../fixtures/local');
        $this->globalConfigPath = realpath(__DIR__ . '/../fixtures/home');

        $this->config = new Config(false, $this->localConfigPath);
        $this->config->merge([
            'config' => [
                'home' => $this->globalConfigPath,
            ],
        ]);

        $package = new RootPackage('my/project', '1.0.0', '1.0.0');
        $package->setExtra([
            GeoIP2UpdatePlugin::EXTRA_KEY => [
                'maxmind-account-id' => '123456',
                'maxmind-license-key' => 'test-key',
                'maxmind-database-editions' => ['GeoLite2-Country'],
                'maxmind-database-folder' => 'var/maxmind',
            ],
        ]);

        $this->composer = new Composer();
        $this->composer->setConfig($this->config);
        $this->composer->setPackage($package);

        $this->configBuilder = new ConfigBuilder();
        $this->SUT = new ConfigLocator($this->configBuilder);
    }

    public function testItLocatesLocalConfig()
    {
        $config = $this->SUT->locate($this->composer);

        $this->assertNotNull($config);
        $this->assertEquals('123456', $config->getAccountId());
        $this->assertEquals('test-key', $config->getLicenseKey());
        $this->assertEquals(['GeoLite2-Country'], $config->getEditions());
        $this->assertEquals('var/maxmind', $config->getDatabaseFolder());
    }

    public function testItLocatesGlobalConfig()
    {
        // Remove local config
        $package = new RootPackage('my/project', '1.0.0', '1.0.0');
        $package->setExtra([]);
        $this->composer->setPackage($package);

        $config = $this->SUT->locate($this->composer);

        $this->assertNotNull($config);
        $this->assertEquals('123456', $config->getAccountId());
        $this->assertEquals('7PwKPrsNmfaDK7X0YiMzwvtyKyFznjbUvKssw0GW', $config->getLicenseKey());
        // Default editions when not specified
        $this->assertEquals(['GeoLite2-Country'], $config->getEditions());
    }

    public function testItDoesNotLocateNonExistingConfig()
    {
        // Remove all configs
        $package = new RootPackage('my/project', '1.0.0', '1.0.0');
        $package->setExtra([]);
        $this->composer->setPackage($package);

        // Set home to non-existing path
        $this->config->merge([
            'config' => [
                'home' => '/tmp/non-existing-path',
            ],
        ]);

        $config = $this->SUT->locate($this->composer);

        $this->assertNull($config);
    }
}
