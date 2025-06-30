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

use danielsreichenbach\GeoIP2Update\Model\Config;

class ConfigBuilder
{
    private const GEOLITE_DB_ASN = 'GeoLite2-ASN';
    private const GEOLITE_DB_COUNTRY = 'GeoLite2-Country';
    private const GEOLITE_DB_CITY = 'GeoLite2-City';

    private const VALID_GEOLITE_DB_VALUES = [
        self::GEOLITE_DB_ASN,
        self::GEOLITE_DB_COUNTRY,
        self::GEOLITE_DB_CITY,
    ];

    /** @var string[] */
    private array $warnings = [];

    /**
     * @param array<string, mixed> $extra
     */
    public function build(array $extra, ?string $baseDir): Config
    {
        $this->reset();

        $maxmindAccountId = '';
        $maxmindLicenseKey = '';
        $maxmindDatabaseEditions = [];
        $maxmindDatabaseFolder = 'var/maxmind';

        if (array_key_exists('maxmind-account-id', $extra)) {
            if (0 === strlen(trim($extra['maxmind-account-id']))) {
                $this->warnings[] = '"maxmind-account-id" is specified but empty. Please add a valid account ID.';
            } else {
                $maxmindAccountId = $extra['maxmind-account-id'];
            }
        } else {
            $this->warnings[] = '"maxmind-account-id" is not specified. Please add a valid account ID.';
        }

        if (array_key_exists('maxmind-license-key', $extra)) {
            if (0 === strlen(trim($extra['maxmind-license-key']))) {
                $this->warnings[] = '"maxmind-license-key" is specified but empty. Please add a valid account ID.';
            } else {
                $maxmindLicenseKey = $extra['maxmind-license-key'];
            }
        } else {
            $this->warnings[] = '"maxmind-license-key" is not specified. Please add a valid account ID.';
        }

        if (array_key_exists('maxmind-database-editions', $extra)) {
            if (!is_array($extra['maxmind-database-editions'])) {
                $this->warnings[] = '"maxmind-database-editions" is specified but should be an array. Ignoring.';
            } else {
                foreach ($extra['maxmind-database-editions'] as $maxmindDatabaseEdition) {
                    if (in_array($maxmindDatabaseEdition, self::VALID_GEOLITE_DB_VALUES, true)) {
                        if (!\in_array($maxmindDatabaseEdition, $maxmindDatabaseEditions, true)) {
                            $maxmindDatabaseEditions[] = $maxmindDatabaseEdition;
                        }
                    } else {
                        $this->warnings[] = sprintf(
                            'Invalid value "%s" for option "%s", defaulting to "%s".',
                            $maxmindDatabaseEdition,
                            'maxmind-database-editions',
                            sprintf('Valid options are "%s".', implode('", "', self::VALID_GEOLITE_DB_VALUES))
                        );
                    }
                }
            }
        }

        if (0 === count($maxmindDatabaseEditions)) {
            $maxmindDatabaseEditions[] = self::GEOLITE_DB_COUNTRY;
        }

        if (array_key_exists('maxmind-database-folder', $extra)) {
            if (0 === strlen(trim($extra['maxmind-database-folder']))) {
                $this->warnings[] = '"maxmind-database-folder" is specified but empty. Ignoring.';
            } else {
                $maxmindDatabaseFolder = $extra['maxmind-database-folder'];
            }
        }

        return new Config(
            $maxmindAccountId,
            $maxmindLicenseKey,
            $maxmindDatabaseEditions,
            $maxmindDatabaseFolder,
        );
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    private function reset(): void
    {
        $this->warnings = [];
    }
}
