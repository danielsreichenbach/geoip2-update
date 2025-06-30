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

use danielsreichenbach\GeoIP2Update\Config\ConfigBuilder;
use PHPUnit\Framework\TestCase;

class ConfigBuilderTest extends TestCase
{
    /** @var ConfigBuilder */
    private $SUT;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->SUT = new ConfigBuilder();
    }

    public function testItHasADefaultSetup()
    {
        $extra = [];

        $config = $this->SUT->build($extra, __DIR__);

        $this->assertInstanceOf('danielsreichenbach\GeoIP2Update\Model\Config', $config);
        $this->assertSame('', $config->getMaxmindAccountId());
        $this->assertSame('', $config->getMaxmindLicenseKey());
        $this->assertCount(1, $config->getMaxmindDatabaseEditions());
        $this->assertEquals('var/maxmind', $config->getMaxmindDatabaseFolder());

        $this->assertCount(2, $this->SUT->getWarnings());
    }

    public function testItSelectsCountryDatabaseAsDefault()
    {
        $extra = [];

        $config = $this->SUT->build($extra, __DIR__);
        $this->assertInstanceOf('danielsreichenbach\GeoIP2Update\Model\Config', $config);

        $this->assertCount(1, $config->getMaxmindDatabaseEditions());
        $this->assertContains('GeoLite2-Country', $config->getMaxmindDatabaseEditions());
    }

    public function testItWarnsWhenAllSettingsAreInvalid()
    {
        $extra = [
            'maxmind-account-id' => '',
            'maxmind-license-key' => '',
            'maxmind-database-editions' => '',
            'maxmind-database-folder' => '',
        ];

        $config = $this->SUT->build($extra, __DIR__);
        $this->assertInstanceOf('danielsreichenbach\GeoIP2Update\Model\Config', $config);

        $this->assertCount(4, $this->SUT->getWarnings());
    }

    public function testItWarnsWhenDatabaseEditionsIsNotAnArray()
    {
        $extra = [
            'maxmind-account-id' => '123456',
            'maxmind-license-key' => '7PwKPrsNmfaDK7X0YiMzwvtyKyFznjbUvKssw0GW',
            'maxmind-database-editions' => 'GeoLite2-ASN',
        ];

        $config = $this->SUT->build($extra, __DIR__);
        $this->assertInstanceOf('danielsreichenbach\GeoIP2Update\Model\Config', $config);

        $this->assertCount(1, $this->SUT->getWarnings());
    }

    public function testItWarnsWhenDatabaseEditionIsInvalid()
    {
        $extra = [
            'maxmind-account-id' => '123456',
            'maxmind-license-key' => '7PwKPrsNmfaDK7X0YiMzwvtyKyFznjbUvKssw0GW',
            'maxmind-database-editions' => ['GeoLite2-FOO'],
        ];

        $config = $this->SUT->build($extra, __DIR__);
        $this->assertInstanceOf('danielsreichenbach\GeoIP2Update\Model\Config', $config);

        $this->assertCount(1, $this->SUT->getWarnings());
        $this->assertStringContainsString('Invalid value "GeoLite2-FOO" for option "maxmind-database-editions"', $this->SUT->getWarnings()[0]);
    }

    public function testItDoesNotAddDatabaseEditionsRepeatedly()
    {
        $extra = [
            'maxmind-database-editions' => ['GeoLite2-City', 'GeoLite2-City'],
        ];

        $config = $this->SUT->build($extra, __DIR__);
        $this->assertInstanceOf('danielsreichenbach\GeoIP2Update\Model\Config', $config);

        $this->assertCount(1, $config->getMaxmindDatabaseEditions());
    }

    public function testItAcceptsAValidSetup()
    {
        $extra = [
            'maxmind-account-id' => '123456',
            'maxmind-license-key' => '7PwKPrsNmfaDK7X0YiMzwvtyKyFznjbUvKssw0GW',
            'maxmind-database-editions' => ['GeoLite2-City'],
            'maxmind-database-folder' => 'data',
        ];

        $config = $this->SUT->build($extra, __DIR__);

        $this->assertInstanceOf('danielsreichenbach\GeoIP2Update\Model\Config', $config);
        $this->assertSame('123456', $config->getMaxmindAccountId());
        $this->assertSame('7PwKPrsNmfaDK7X0YiMzwvtyKyFznjbUvKssw0GW', $config->getMaxmindLicenseKey());
        $this->assertCount(1, $config->getMaxmindDatabaseEditions());
        $this->assertEquals('data', $config->getMaxmindDatabaseFolder());

        $this->assertCount(0, $this->SUT->getWarnings());
    }
}
