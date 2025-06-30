# geoip2-update

[![Total Downloads](https://poser.pugx.org/danielsreichenbach/geoip2-update/downloads)](https://packagist.org/packages/danielsreichenbach/geoip2-update)
[![Latest Stable Version](https://poser.pugx.org/danielsreichenbach/geoip2-update/v/stable)](https://packagist.org/packages/danielsreichenbach/geoip2-update)

geoip2-update is a Composer plugin that automatically downloads and updates MaxMind's GeoIP2 and GeoLite2 databases for use in your projects.

## Features

- 🔄 **Automatic Updates**: Databases are automatically updated after `composer install` and `composer update` commands
- 🎯 **Manual Updates**: Use `composer geoip2:update` command for on-demand updates
- ⚡ **Smart Updates**: Only downloads new versions when updates are available
- 🎪 **Event-Driven**: Extensible architecture with custom event listeners
- 🧪 **Fully Tested**: Comprehensive unit and integration test coverage
- 📦 **Zero Dependencies**: Only requires Composer itself

## Installation

```shell
composer require danielsreichenbach/geoip2-update:^3
```

If you are using PHP `< 8.x`, you may want to install version 2.x instead:

```shell
composer require danielsreichenbach/geoip2-update:^2
```

Note that version 2.x is not receiving updates any longer and was only meant to resolve the issues within the original project.

## Configuration

Configure your MaxMind account credentials in your project's `composer.json`:

```json
{
    "extra": {
        "geoip2-update": {
            "maxmind-account-id": "YOUR_ACCOUNT_ID",
            "maxmind-license-key": "YOUR_LICENSE_KEY",
            "maxmind-database-editions": ["GeoLite2-Country", "GeoLite2-City"],
            "maxmind-database-folder": "var/maxmind"
        }
    }
}
```

### Configuration Options

| Option | Description | Default |
|--------|-------------|---------|
| `maxmind-account-id` | Your MaxMind account ID | *(required)* |
| `maxmind-license-key` | Your MaxMind license key | *(required)* |
| `maxmind-database-editions` | Array of database editions to download | `["GeoLite2-Country"]` |
| `maxmind-database-folder` | Directory to store databases | `var/maxmind` |

Available editions: `GeoLite2-ASN`, `GeoLite2-City`, `GeoLite2-Country`

## Usage

### Automatic Updates

Once configured, databases will be automatically updated when you run:

- `composer install` (in dev mode)
- `composer update` (in dev mode)

### Manual Updates

To manually update databases:

```shell
# Update all configured databases
composer geoip2:update

# Force update even if already up-to-date
composer geoip2:update --force

# Update specific editions only
composer geoip2:update --edition GeoLite2-City --edition GeoLite2-ASN
```

### Programmatic Usage

You can also use the library programmatically:

```php
use danielsreichenbach\GeoIP2Update\Factory;

// Create configuration
$config = new \danielsreichenbach\GeoIP2Update\Model\Config(
    'YOUR_ACCOUNT_ID',
    'YOUR_LICENSE_KEY',
    ['GeoLite2-Country', 'GeoLite2-City'],
    '/path/to/databases'
);

// Create updater and run update
$updater = Factory::createDatabaseUpdater();
$results = $updater->update($config);

// Check results
foreach ($results as $edition => $result) {
    if ($result['success']) {
        echo "$edition: {$result['message']}\n";
    } else {
        echo "$edition failed: {$result['message']}\n";
    }
}
```

## Event System

The plugin dispatches events during the update process that you can listen to:

### Available Events

- `geoip2.pre_update` - Fired before any updates begin
- `geoip2.database_update` - Fired before each database update
- `geoip2.post_update` - Fired after all updates complete
- `geoip2.update_error` - Fired when an error occurs

### Example Event Listener

```php
use danielsreichenbach\GeoIP2Update\Event\DatabaseUpdateEvent;
use danielsreichenbach\GeoIP2Update\Event\PostUpdateEvent;

class MyUpdateListener
{
    public static function onDatabaseUpdate(DatabaseUpdateEvent $event): void
    {
        echo "Updating {$event->getEdition()}...\n";
        
        // Skip specific database
        if ($event->getEdition() === 'GeoLite2-ASN') {
            $event->skipDatabase();
        }
    }
    
    public static function onPostUpdate(PostUpdateEvent $event): void
    {
        echo "Updates complete! Success: {$event->getSuccessCount()}, Failed: {$event->getFailureCount()}\n";
    }
}
```

Register your listener in `composer.json`:

```json
{
    "scripts": {
        "geoip2.database_update": "MyUpdateListener::onDatabaseUpdate",
        "geoip2.post_update": "MyUpdateListener::onPostUpdate"
    }
}
```

## Architecture

The plugin follows a modular architecture with clear separation of concerns:

- **Core Components**: Handle downloading, extraction, and file management
- **Event System**: Provides extensibility through custom events
- **Composer Integration**: Seamless integration as a Composer plugin
- **Factory Pattern**: Centralized component creation with singleton management

For more details, see the [architecture documentation](doc/architecture.md).

## Upgrading from 2.x to 3.x

Version 3.x is a complete rewrite with a new architecture. See the [upgrade guide](UPGRADING.md) for migration instructions.

## License

This project is made available under the terms of the [MIT license](LICENSE.md).

Copyright for versions prior to 3.x is owned by [Andrey Tronov][], with any version from 3.x onwards being owned by [Daniel S. Reichenbach][] since no code from 2.x remains in 3.x.

## History

This package was originally based on [tronovav/geoip2-update][] as the upstream package introduced an intermediary proxy to download database updates instead of using the official [MaxMind, Inc.][] update API.

From version 3.x onwards, the original code has been completely replaced with a modern, test-driven implementation as a proper [Composer plugin][].

[MaxMind, Inc.]: https://www.maxmind.com/
[Andrey Tronov]: https://github.com/tronovav
[Daniel S. Reichenbach]: https://github.com/danielsreichenbach
[tronovav/geoip2-update]: https://github.com/tronovav/geoip2-update
[Composer plugin]: https://getcomposer.org/doc/articles/plugins.md