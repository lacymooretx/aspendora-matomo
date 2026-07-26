# Aspendora Matomo — custom image built from our fork (branch `aspendora`, based on 5.12.0).
# Mirrors the aspendora-crm pattern: source overlaid onto the official runtime image so our
# modifications ship.
#
# IMPORTANT: the official matomo image declares `VOLUME /var/www/html`, so COPYing source
# into /var/www/html in a child Dockerfile is silently DISCARDED at build time. The source
# must instead replace /usr/src/matomo — the official entrypoint copies it into the (empty)
# webroot volume on container start. config/tmp/misc are named volumes (see compose).

FROM composer:2 AS vendor
COPY . /src
WORKDIR /src
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

FROM matomo:5-apache
RUN rm -rf /usr/src/matomo
COPY --from=vendor --chown=www-data:www-data /src /usr/src/matomo

# Pristine config (no config.ini.php in git source) for the startup re-sync
RUN cp -a /usr/src/matomo/config /usr/src/matomo-config
COPY deploy/docker-entrypoint-aspendora.sh /docker-entrypoint-aspendora.sh
RUN chmod +x /docker-entrypoint-aspendora.sh
ENTRYPOINT ["/docker-entrypoint-aspendora.sh"]
CMD ["apache2-foreground"]
