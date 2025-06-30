<?php

/*
 * This file is part of the geoip2-update project.
 *
 * (c) Daniel S. Reichenbach <daniel@kogito.network>
 *
 * For the full copyright and license information, please view the LICENSE.MD
 * file that was distributed with this source code.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Suppress E_STRICT deprecation warnings in PHP 8.4
// These are caused by Composer's internal use of the deprecated E_STRICT constant
if (PHP_VERSION_ID >= 80400) {
    error_reporting(E_ALL & ~E_DEPRECATED);
}

// Disable PHPUnit's deprecation handler for Composer-related deprecations
if (class_exists('PHPUnit\Util\Error\Handler')) {
    PHPUnit\Util\Error\Handler::$enabled = false;
}