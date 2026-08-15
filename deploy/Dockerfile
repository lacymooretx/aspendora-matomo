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

# IMPORTANT: our source tree carries Matomo's git submodules as EMPTY directories (they are
# never `submodule update --init`ed for a build), so replacing /usr/src/matomo wholesale drops
# everything the release tarball ships from a submodule. Most visibly plugins/Morpheus/icons —
# every browser/OS/device/country/plugin icon in the UI, which 404s and renders as a broken
# image in the visitor log. Keep the upstream tree aside and restore any bundled path our
# checkout left empty. Base tag and fork base must stay on the same Matomo version (5.12.0).
FROM matomo:5-apache
RUN mv /usr/src/matomo /usr/src/matomo-upstream
COPY --from=vendor --chown=www-data:www-data /src /usr/src/matomo
RUN set -eux; \
    for p in plugins/Morpheus/icons plugins/TagManager misc/log-analytics; do \
      if [ -z "$(ls -A "/usr/src/matomo/$p" 2>/dev/null || true)" ]; then \
        mkdir -p "/usr/src/matomo/$p"; \
        cp -a "/usr/src/matomo-upstream/$p/." "/usr/src/matomo/$p/"; \
        chown -R www-data:www-data "/usr/src/matomo/$p"; \
      fi; \
    done; \
    rm -rf /usr/src/matomo-upstream

# Blocker-safe alias: the rrweb recorder ships as /vendor.js (loaded by bundle.js from the
# bland first-party hub domains; "rrweb" in a URL would trip content-blocker filename rules)
RUN cp /usr/src/matomo/plugins/AspendoraSessionRecording/assets/rrweb.min.js /usr/src/matomo/vendor.js

# Brand typeface for PDF reports. TCPDF can't read a TTF directly — it needs a converted
# .php/.z/.ctg.z trio in its own fonts dir — so the conversion is done here, at build time,
# rather than on first render. Produces the font keys `plusjakartasans` and `plusjakartasansb`
# (Pdf.php asks for style 'B' on table headers), which is what
# ReportRenderer::DEFAULT_REPORT_FONT_FAMILY points at.
# NOTE the trailing slash on the out path: TCPDF concatenates it straight onto the font name,
# so without it the files land next to the directory instead of inside it.
RUN php -r '\
    require "/usr/src/matomo/vendor/autoload.php"; \
    $out = "/usr/src/matomo/vendor/tecnickcom/tcpdf/fonts/"; \
    foreach (["Regular", "Bold"] as $weight) { \
        $ttf = "/usr/src/matomo/plugins/AspendoraTheme/fonts/PlusJakartaSans-$weight.ttf"; \
        $key = TCPDF_FONTS::addTTFfont($ttf, "TrueTypeUnicode", "", 32, $out); \
        if (!$key || !file_exists($out . $key . ".php")) { \
            fwrite(STDERR, "font conversion failed for $ttf\n"); exit(1); \
        } \
        echo "converted $weight -> $key\n"; \
    }'

# Pristine config (no config.ini.php in git source) for the startup re-sync
RUN cp -a /usr/src/matomo/config /usr/src/matomo-config
COPY deploy/docker-entrypoint-aspendora.sh /docker-entrypoint-aspendora.sh
RUN chmod +x /docker-entrypoint-aspendora.sh
ENTRYPOINT ["/docker-entrypoint-aspendora.sh"]
CMD ["apache2-foreground"]
