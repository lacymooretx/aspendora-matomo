#!/bin/sh
# Refresh version-owned config files (global.ini.php, global.php, environment/) from the image
# into the config volume on every start, so upgrades never run against stale globals.
# config.ini.php (instance data) is not in the pristine copy and is left untouched.
set -e
cp -a /usr/src/matomo-config/. /var/www/html/config/
# stale release manifest would fail integrity checks against our source build
rm -f /var/www/html/config/manifest.inc.php
chown -R www-data:www-data /var/www/html/config
exec /entrypoint.sh "$@"
