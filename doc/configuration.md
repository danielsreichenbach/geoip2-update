# Plugin configuration

This plugin requires some configuration - for example to specify your MaxMind
account ID and license key.

## Location

Configuration can be setup by adding the parameters in the `extra` section
of your `composer.json`.

```json
{
    "extra": {
        "geoip2-update": {
            "{{ the configuration key }}": "{{ the configuration value }}",
        }
    }
}
```

This `geoip2-update` extra config can be put either in your local
`composer.json` (the one of the project you are working on) or the global one
in your `.composer` home directory (like `/home/{user}/.composer/composer.json`
on Linux).

## Configuration available

The available configuration options are listed below:

### MaxMind account and license

The account ID and license key can be retrieved from your [MaxMind, Inc.][]
account under "Manage License Keys".

If you do not yet have an account, you may create a free account which allows
to download the GeoLite2 database offering up to _five_ times per day.

```json
{
    "extra": {
        "geoip2-update": {
            "maxmind-account-id": "123456",
            "maxmind-license-key": "7PwKPrsNmfaDK7X0YiMzwvtyKyFznjbUvKssw0GW"
        }
    }
}
```

This configurations is mandatory, since the MaxMind update API requires account
information.

### MaxMind database edition(s)

[GeoLite2 Free Geolocation Data][GeoLite2] is provided in three editions with
varying accuracy:

- `GeoLite2-ASN`: least accurate
- `GeoLite2-Country`: more accurate
- `GeoLite2-City`: most accurate

Depending on your applications use cases, you can configure which edition(s)
are necessary.

The default configuration will retrieve the `GeoLite2-Country` edition. You can
select one or more editions for your project, e.g.

```json
{
    "extra": {
        "geoip2-update": {
            "maxmind-database-editions": [
                "GeoLite2-ASN",
                "GeoLite2-Country",
                "GeoLite2-City"
            ]
        }
    }
}
```

Specifying an unknown and/or invalid edition will raise an error.

## MaxMind database folder

In order to keep the database stored, a folder needs to be specified:

```json
{
    "extra": {
        "geoip2-update": {
            "maxmind-database-folder": "var/maxmind"
        }
    }
}
```

Note that the plugin will ensure the directory exists and is accessible. If
that is not the case, it will raise an error.

[MaxMind, Inc.]: https://www.maxmind.com/
[GeoLite2]: https://dev.maxmind.com/geoip/geolite2-free-geolocation-data
