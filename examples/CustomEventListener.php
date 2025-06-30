<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

namespace MyProject;

use danielsreichenbach\GeoIP2Update\Event\DatabaseUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\PostUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\PreUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\UpdateErrorEvent;

/**
 * Example event listener for GeoIP2 Update events.
 *
 * This class demonstrates how to listen to and respond to events
 * fired during the GeoIP2 database update process.
 */
class CustomEventListener
{
    /**
     * Handle pre-update event.
     *
     * This method is called before any database updates begin.
     * You can use it to prepare for updates or modify configuration.
     */
    public static function onPreUpdate(PreUpdateEvent $event): void
    {
        $editions = $event->getConfig()->getEditions();

        echo sprintf(
            "Starting GeoIP2 database updates for %d edition(s): %s\n",
            count($editions),
            implode(', ', $editions)
        );

        // Example: Log to a file
        file_put_contents(
            'geoip2-updates.log',
            sprintf("[%s] Starting updates for: %s\n", date('Y-m-d H:i:s'), implode(', ', $editions)),
            FILE_APPEND
        );
    }

    /**
     * Handle database update event.
     *
     * This method is called before each individual database update.
     * You can use it to skip certain databases or track progress.
     */
    public static function onDatabaseUpdate(DatabaseUpdateEvent $event): void
    {
        $edition = $event->getEdition();

        echo sprintf("Updating %s...\n", $edition);

        // Example: Skip updates for specific editions
        if ('GeoLite2-ASN' === $edition && 'true' === getenv('SKIP_ASN_UPDATE')) {
            echo "  Skipping ASN database update (SKIP_ASN_UPDATE=true)\n";
            $event->skipDatabase();

            return;
        }

        // Example: Skip updates during certain hours
        $currentHour = (int) date('H');
        if ($currentHour >= 0 && $currentHour < 6) {
            echo "  Skipping update during maintenance window (00:00-06:00)\n";
            $event->skipDatabase();

            return;
        }
    }

    /**
     * Handle post-update event.
     *
     * This method is called after all database updates complete.
     * You can use it to send notifications or perform cleanup.
     */
    public static function onPostUpdate(PostUpdateEvent $event): void
    {
        $results = $event->getResults();
        $successCount = $event->getSuccessCount();
        $failureCount = $event->getFailureCount();

        echo sprintf(
            "\nUpdate complete! Success: %d, Failed: %d\n",
            $successCount,
            $failureCount
        );

        // Example: Send notification email if there were failures
        if ($failureCount > 0) {
            // mail('admin@example.com', 'GeoIP2 Update Failures', ...);

            echo "\nFailed updates:\n";
            foreach ($results as $edition => $result) {
                if (!$result['success']) {
                    echo sprintf("  - %s: %s\n", $edition, $result['message']);
                }
            }
        }

        // Example: Log results
        file_put_contents(
            'geoip2-updates.log',
            sprintf(
                "[%s] Updates complete. Success: %d, Failed: %d\n",
                date('Y-m-d H:i:s'),
                $successCount,
                $failureCount
            ),
            FILE_APPEND
        );

        // Example: Update version tracking in database
        foreach ($results as $edition => $result) {
            if ($result['success'] && isset($result['newVersion'])) {
                // Update your application's database with new version info
                // $db->updateGeoIPVersion($edition, $result['newVersion']);
            }
        }
    }

    /**
     * Handle update error event.
     *
     * This method is called when an error occurs during updates.
     * You can use it for error tracking or alerting.
     */
    public static function onUpdateError(UpdateErrorEvent $event): void
    {
        $edition = $event->getEdition();
        $error = $event->getError();

        echo sprintf(
            "\nERROR updating %s: %s\n",
            $edition,
            $error->getMessage()
        );

        // Example: Send to error tracking service
        // Sentry::captureException($error, ['edition' => $edition]);

        // Example: Log detailed error
        file_put_contents(
            'geoip2-errors.log',
            sprintf(
                "[%s] Error updating %s: %s\nTrace: %s\n\n",
                date('Y-m-d H:i:s'),
                $edition,
                $error->getMessage(),
                $error->getTraceAsString()
            ),
            FILE_APPEND
        );
    }
}
