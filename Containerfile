# SendLock — OpenShift-ready production image.
#
# Multi-stage build:
#   1. assets  — Node builds the Vite bundle (public/build is gitignored).
#   2. vendor  — Composer installs production PHP dependencies.
#   3. runtime — Red Hat UBI9 PHP 8.3 + Apache (s2i image used as a plain base).
#
# The runtime image serves on port 8080 as a non-root user and tolerates
# OpenShift's arbitrary-UID assignment (files are owned by group 0 with
# group-write, per the OpenShift image guidelines). Runtime bootstrap
# (config/route/view caching against the real environment) happens in
# docker/start.sh, NOT at build time — env vars only exist at runtime.
#
# Build:  podman build -f Containerfile -t sendlock:latest .
#         (or let the OpenShift BuildConfig in openshift/buildconfig.yaml do it)

# --- Stage 1: frontend assets -------------------------------------------------
FROM docker.io/library/node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
# Vite + Tailwind need the source tree (blade templates are scanned for classes).
COPY . .
RUN npm run build

# --- Stage 2: PHP dependencies ------------------------------------------------
FROM docker.io/library/composer:2 AS vendor
WORKDIR /app
COPY . .
# --no-scripts: artisan package:discover would bootstrap the app inside the
# composer image; the manifest is regenerated in the runtime stage instead.
RUN composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress --no-scripts

# --- Stage 3: runtime ---------------------------------------------------------
FROM registry.access.redhat.com/ubi9/php-83:latest AS runtime

# The s2i php image serves ${APP_ROOT}/src (= /opt/app-root/src) via Apache on
# 8080; DOCUMENTROOT points Apache at Laravel's public/ directory.
ENV DOCUMENTROOT=/public \
    COMPOSER_ALLOW_SUPERUSER=1

USER 0
WORKDIR /opt/app-root/src

COPY --chown=1001:0 . .
COPY --chown=1001:0 --from=vendor /app/vendor ./vendor
COPY --chown=1001:0 --from=assets /app/public/build ./public/build
# The UBI php base has no composer (s2i normally downloads it at assemble time).
COPY --from=vendor /usr/bin/composer /usr/local/bin/composer

# Regenerate the autoloader + package manifest inside the final tree, then make
# the writable paths group-0 writable so an arbitrary OpenShift UID can use them.
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chgrp -R 0 storage bootstrap/cache \
    && chmod -R g=u storage bootstrap/cache \
    && chmod +x docker/start.sh

USER 1001
EXPOSE 8080

CMD ["/opt/app-root/src/docker/start.sh"]
