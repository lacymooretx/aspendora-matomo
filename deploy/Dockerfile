# Aspendora Matomo — custom image built from our fork (branch `aspendora`, based on 5.12.0).
# Mirrors the aspendora-crm pattern: source overlaid onto the official runtime image so our
# modifications ship. Code is baked at /var/www/html (the official entrypoint sees matomo.php
# already present and skips its tarball copy); config/tmp/misc live in volumes.

FROM composer:2 AS vendor
COPY . /src
WORKDIR /src
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

FROM matomo:5-apache
COPY --from=vendor --chown=www-data:www-data /src /var/www/html
RUN mkdir -p /var/www/html/tmp /var/www/html/config /var/www/html/misc \
    && chown -R www-data:www-data /var/www/html/tmp /var/www/html/config /var/www/html/misc

# Pristine config (no config.ini.php in git source) for the startup re-sync
RUN cp -a /var/www/html/config /usr/src/matomo-config
COPY deploy/docker-entrypoint-aspendora.sh /docker-entrypoint-aspendora.sh
RUN chmod +x /docker-entrypoint-aspendora.sh
ENTRYPOINT ["/docker-entrypoint-aspendora.sh"]
CMD ["apache2-foreground"]
