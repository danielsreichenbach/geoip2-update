<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace danielsreichenbach\GeoIP2Update\Command;

use Composer\Command\BaseCommand;
use danielsreichenbach\GeoIP2Update\Config\ConfigBuilder;
use danielsreichenbach\GeoIP2Update\Config\ConfigLocator;
use danielsreichenbach\GeoIP2Update\Factory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UpdateCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('geoip2:update')
            ->setDescription('Update GeoIP2/GeoLite2 databases')
            ->setHelp('This command allows you to manually update your GeoIP2/GeoLite2 databases')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force update even if databases are up to date'
            )
            ->addOption(
                'edition',
                'e',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Specific editions to update (can be used multiple times)',
                []
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getIO();
        $composer = $this->getComposer();

        if (null === $composer) {
            $io->writeError('<error>Composer instance not available.</error>');

            return 1;
        }

        $configBuilder = new ConfigBuilder();
        $configLocator = new ConfigLocator($configBuilder);

        $config = $configLocator->locate($composer);

        if (null === $config) {
            $io->writeError('<error>No GeoIP2 Update configuration found.</error>');
            $io->write('');
            $io->write('Please add configuration to your composer.json:');
            $io->write('');
            $io->write('{');
            $io->write('    "extra": {');
            $io->write('        "geoip2-update": {');
            $io->write('            "maxmind-account-id": "YOUR_ACCOUNT_ID",');
            $io->write('            "maxmind-license-key": "YOUR_LICENSE_KEY",');
            $io->write('            "maxmind-database-editions": ["GeoLite2-Country", "GeoLite2-City"],');
            $io->write('            "maxmind-database-folder": "var/maxmind"');
            $io->write('        }');
            $io->write('    }');
            $io->write('}');

            return 1;
        }

        $warnings = $configBuilder->getWarnings();
        if (count($warnings) > 0) {
            $io->write('<warning>Configuration warnings:</warning>');
            foreach ($warnings as $warning) {
                $io->write(sprintf('  - %s', $warning));
            }
            $io->write('');
        }

        if (!$config->isValid()) {
            $io->writeError('<error>GeoIP2 Update configuration is invalid.</error>');

            return 1;
        }

        $force = $input->getOption('force');
        $specificEditions = $input->getOption('edition');

        $editions = empty($specificEditions) ? $config->getEditions() : $specificEditions;

        // Create a modified config if specific editions are requested
        if (!empty($specificEditions)) {
            $config = new \danielsreichenbach\GeoIP2Update\Model\Config(
                $config->getAccountId(),
                $config->getLicenseKey(),
                $specificEditions,
                $config->getDatabaseFolder()
            );
        }

        $io->write('<info>Updating GeoIP2 databases...</info>');
        $io->write(sprintf('Account ID: %s', $config->getAccountId()));
        $io->write(sprintf('Database folder: %s', $config->getDatabaseFolder()));
        $io->write(sprintf('Editions: %s', implode(', ', $editions)));
        $io->write('');

        // Validate database directory
        $fileManager = Factory::createFileManager();
        $errors = $fileManager->validateDirectory($config->getDatabaseFolder());

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $io->writeError(sprintf('<error>%s</error>', $error));
            }

            return 1;
        }

        // Create event dispatcher (null composer means no Composer event integration)
        $eventDispatcher = Factory::createEventDispatcher(null, $io);

        // Update databases with progress display
        $updater = Factory::createDatabaseUpdater($eventDispatcher, $io);
        $results = $updater->update($config, $force);

        // Display results
        $hasErrors = false;
        $updatedCount = 0;
        $skippedCount = 0;

        foreach ($results as $edition => $result) {
            if ($result['success']) {
                if (isset($result['oldVersion'], $result['newVersion']) && $result['oldVersion'] !== $result['newVersion']) {
                    $io->write(sprintf(
                        '<info>✓ %s (updated from %s to %s)</info>',
                        $result['message'],
                        $result['oldVersion'] ?: 'none',
                        $result['newVersion']
                    ));
                    ++$updatedCount;
                } else {
                    $io->write(sprintf('<comment>✓ %s</comment>', $result['message']));
                    ++$skippedCount;
                }
            } else {
                $hasErrors = true;
                $io->writeError(sprintf(
                    '<error>✗ %s</error>',
                    $result['message']
                ));
            }
        }

        $io->write('');
        if ($updatedCount > 0) {
            $io->write(sprintf('<info>Updated %d database%s.</info>', $updatedCount, $updatedCount > 1 ? 's' : ''));
        }
        if ($skippedCount > 0) {
            $io->write(sprintf('<comment>Skipped %d database%s (already up to date).</comment>', $skippedCount, $skippedCount > 1 ? 's' : ''));
        }
        if ($hasErrors) {
            $io->writeError('<error>Some databases failed to update.</error>');
        }

        return $hasErrors ? 1 : 0;
    }
}
