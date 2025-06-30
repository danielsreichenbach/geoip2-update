# geoip2-update

[![Total Downloads](https://poser.pugx.org/danielsreichenbach/geoip2-update/downloads)](https://packagist.org/packages/danielsreichenbach/geoip2-update)
[![Latest Stable Version](https://poser.pugx.org/danielsreichenbach/geoip2-update/v/stable)](https://packagist.org/packages/danielsreichenbach/geoip2-update)

geoip2-update is a plugin for Composer. It allows to retrieve any of the
GeoLite2 databases provided by [MaxMind, Inc.][] for use in your project.

The primary intention is to provide a functional equivalent to the official
[geoipupdate][] tool for scenarios where an integrated tool may be beneficial.

An example would be using the Symfony [BazingaGeocoderBundle][geocoder-bundle]
which supports GeoLite2 as a data source but provides no means to acquire a
copy of the databases.

## Installation

```shell
composer require danielsreichenbach/geoip2-update:^3
```

If you are using PHP `< 8.x`, you may want to install version 2.x instead:

```shell
composer require danielsreichenbach/geoip2-update:^2
```

Note that version 2.x is not receiving updates any longer and was only meant
to resolve the issues within the original project.

## Upgrading from 2.x to 3.x

Since version 3.x is a full rewrite of 2.x, there have been changes incompatible
with version 2.x.

To help with the upgrade process, a dedicated [upgrade guide](UPGRADING.md) has
been prepared.

## Configuration

In order to retrieve database updates, you will need to configure your MaxMind
account ID and license key in the projects `composer.json` (or in your global
Composer configuration).

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

Further [configuration options](doc/configuration.md) are available, and - while
not mandatory - should be customized per project.

## License

This project is made available under the terms of the [MIT license](LICENSE.md).

Copyright for versions prior to 3.x is owned by [Andrey Tronov][], with any
version from 3.x onwards being owned by [Daniel S. Reichenbach][] since no
code from 2.x remains in 3.x.

## History

This packages was original based on [tronovav/geoip2-update][] as the upstream
package introduced an intermediary proxy to download database updates instead of
using the official [MaxMind, Inc.][] update API.

Any release tagged with version 2.x is based on the work of [Andrey Tronov][]
with modifications to use the official MaxMind update API.

From version 3 on forwards, the original code has been removed and the former
library has been converted into a [Composer plugin][], replacing any original
code with improved, testable variants.

[MaxMind, Inc.]: https://www.maxmind.com/
[geoipupdate]: https://github.com/maxmind/geoipupdate
[geocoder-bundle]: https://github.com/geocoder-php/BazingaGeocoderBundle
[tronovav/geoip2-update]: https://github.com/tronovav/geoip2-update
[Andrey Tronov]: https://github.com/tronovav
[Daniel S. Reichenbach]: https://github.com/danielsreichenbach
[Composer plugin]: https://getcomposer.org/doc/articles/plugins.md
