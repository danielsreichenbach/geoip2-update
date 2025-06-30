<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Config;

use Composer\Composer;
use danielsreichenbach\GeoIP2Update\GeoIP2UpdatePlugin;
use danielsreichenbach\GeoIP2Update\Model\Config;

class ConfigLocator
{
    public function __construct(
        private ConfigBuilder $configBuilder,
    ) {
    }

    /**
     * Locate and build configuration from composer.json.
     */
    public function locate(Composer $composer): ?Config
    {
        $extra = $composer->getPackage()->getExtra();

        if (!isset($extra[GeoIP2UpdatePlugin::EXTRA_KEY])) {
            // Try global config
            $globalConfig = $this->locateGlobal($composer);
            if (null !== $globalConfig) {
                return $globalConfig;
            }

            return null;
        }

        $baseDir = $this->getBaseDir($composer);

        return $this->configBuilder->build($extra[GeoIP2UpdatePlugin::EXTRA_KEY], $baseDir);
    }

    /**
     * Search config in the global root package.
     */
    private function locateGlobal(Composer $composer): ?Config
    {
        $path = $composer->getConfig()->get('home');
        $globalComposerJsonFile = $path . '/composer.json';

        if (file_exists($globalComposerJsonFile)) {
            $globalComposerJson = file_get_contents($globalComposerJsonFile);

            if (!$globalComposerJson) {
                return null;
            }

            try {
                $globalComposerConfig = json_decode($globalComposerJson, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                return null;
            }

            if (isset($globalComposerConfig['extra'][GeoIP2UpdatePlugin::EXTRA_KEY])) {
                return $this->configBuilder->build(
                    $globalComposerConfig['extra'][GeoIP2UpdatePlugin::EXTRA_KEY],
                    $path
                );
            }
        }

        return null;
    }

    /**
     * Get the base directory of the root package.
     */
    private function getBaseDir(Composer $composer): string
    {
        $composerConfig = $composer->getConfig();

        // Use reflection to get the base directory
        $reflection = new \ReflectionClass($composerConfig);
        $property = $reflection->getProperty('baseDir');
        $property->setAccessible(true);

        $baseDir = $property->getValue($composerConfig);

        // If baseDir is not set, use the current working directory
        if (null === $baseDir || '' === $baseDir) {
            $baseDir = getcwd();
        }

        return $baseDir;
    }
}
