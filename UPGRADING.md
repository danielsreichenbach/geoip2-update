# Upgrading geoip2-update

This document is intended to show how to upgrade between major releases of the
geoip2-update project.

## Upgrading from version 2 to version 3

With the rewrite of version 3 and the conversion to a full Composer plugin,
configuration options have been updated too.

In version 2.x, configuration options had to be specified in the `extra` block
of `composer.json` like this:

```json
    "extra": {
        "danielsreichenbach\\GeoIP2Update\\ComposerClient::run": {
            "dir": "var/maxmind",
            "editions": [
                "GeoLite2-City"
            ],
            "account_id": "123456",
            "license_key": "7PwKPrsNmfaDK7X0YiMzwvtyKyFznjbUvKssw0GW"
        }
    }
```

With version 3.x this has been streamlined into a more readable version:

```json
{
    "extra": {
        "geoip2-update": {
            "maxmind-account-id": "123456",
            "maxmind-license-key": "7PwKPrsNmfaDK7X0YiMzwvtyKyFznjbUvKssw0GW",
            "maxmind-database-editions": [
                "GeoLite2-ASN",
                "GeoLite2-Country",
                "GeoLite2-City"
            ],
            "maxmind-database-folder": "var/maxmind"
        }
    }
}
```

The Composer plugin introduced with version 3.x will now also validate any
configuration options, resulting in failures for invalid options, and warnings
for e.g. deprecated options.
