#!/bin/sh
# Refresh version-owned config files (global.ini.php, global.php, environment/) from the image
# into the config volume on every start, so upgrades never run against stale globals.
# config.ini.php (instance data) is not in the pristine copy and is left untouched.
set -e
cp -a /usr/src/matomo-config/. /var/www/html/config/
# stale release manifest would fail integrity checks against our source build
rm -f /var/www/html/config/manifest.inc.php
chown -R www-data:www-data /var/www/html/config

# Tag Manager writes its published container files into the webroot (js/container_*.js), and the
# webroot is an ANONYMOUS volume — the documented redeploy renews it, so every deploy wipes them
# and the live site's container 404s silently until someone notices. Regenerate them once the
# official entrypoint has repopulated the webroot. Backgrounded because that copy takes ~1 min,
# and best-effort: a failure here must never stop the container from starting.
(
    i=0
    while [ $i -lt 60 ]; do
        if [ -f /var/www/html/console ] && [ -f /var/www/html/config/config.ini.php ]; then
            su -s /bin/sh www-data -c \
                'php /var/www/html/console tagmanager:regenerate-released-containers' \
                >/dev/null 2>&1 || true
            break
        fi
        i=$((i + 1))
        sleep 5
    done
) &

exec /entrypoint.sh "$@"
