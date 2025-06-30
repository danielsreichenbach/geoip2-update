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

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;
use danielsreichenbach\GeoIP2Update\Command\UpdateCommand;
use danielsreichenbach\GeoIP2Update\CommandProvider;
use PHPUnit\Framework\TestCase;

class CommandProviderTest extends TestCase
{
    private CommandProvider $commandProvider;

    protected function setUp(): void
    {
        $this->commandProvider = new CommandProvider();
    }

    public function testImplementsCommandProviderInterface(): void
    {
        $this->assertInstanceOf(CommandProviderCapability::class, $this->commandProvider);
    }

    public function testGetCommands(): void
    {
        $commands = $this->commandProvider->getCommands();

        $this->assertIsArray($commands);
        $this->assertCount(1, $commands);
        $this->assertInstanceOf(UpdateCommand::class, $commands[0]);
    }
}
