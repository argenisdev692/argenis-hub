# syntax=docker/dockerfile:1

# Single-stage Railway image. The Vite build MUST run here (not in a
# node-only stage): @laravel/vite-plugin-wayfinder shells out to
# `php artisan wayfinder:generate` on every build, so PHP + vendor/ have to
# exist before `npm run build`. (A node:24 stage fails with "php: not found".)
FROM ubuntu:24.04 AS app

ARG PHP_VERSION=8.6
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ENV DEBIAN_FRONTEND=noninteractive

# PHP 8.6 from packages.sury.org, NOT the official php:8.6 image: that image
# dropped pecl/pear and no longer compiles mbstring in, while the Debian
# packaging used here ships both as ordinary php8.6-* packages (verified:
# php8.6-cli/redis/mbstring 8.6.0-3+debsuryorg24.04.1). The ondrej Launchpad
# PPA is frozen ("no longer receives updates") and still serves 8.6.0-2, so
# security releases would stop; debsuryorg is the maintained repo.
#
# Node 24 via nodesource. The PostgreSQL 17 client is required by
# `backup:run --only-db`: Ubuntu 24.04 ships client 16, and pg_dump refuses a
# newer server (Supabase is 17.6).
RUN mkdir -p /etc/apt/keyrings \
    && apt-get update && apt-get install -y --no-install-recommends \
        gnupg curl ca-certificates git unzip \
    && curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb \
    && dpkg -i /tmp/debsuryorg-archive-keyring.deb \
    && echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ noble main" \
        > /etc/apt/sources.list.d/php.list \
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
        | gpg --dearmor -o /etc/apt/keyrings/pgdg.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] https://apt.postgresql.org/pub/repos/apt noble-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-intl \
        postgresql-client-17 \
        nodejs \
        supervisor \
        ffmpeg \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && apt-get -y autoremove && apt-get clean && rm -rf /var/lib/apt/lists/*

# Limits shared by `artisan serve`, queue:work and schedule:work (see
# docker/php.ini). Without this, uploads are capped at PHP's 2 MB default.
COPY docker/php.ini /etc/php/${PHP_VERSION}/cli/conf.d/99-app.ini

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --optimize-autoloader

# scripts/link-syn-bin.mjs only links scripts/ai-config-sync.mjs into
# node_modules/.bin and no-ops when it is absent — copy it BEFORE npm ci
# so any lifecycle script that needs the bin finds it.
COPY package.json package-lock.json ./
COPY scripts/link-syn-bin.mjs scripts/link-syn-bin.mjs
RUN npm ci

COPY . .

# --no-scripts above skips package:discover and bootstrap/cache/packages.php
# is gitignored, so without this `php artisan wayfinder:generate` (invoked by
# the Vite build below) fails with "command not defined". The mkdir comes
# FIRST: Laravel resolves the view cache path with realpath(), so any artisan
# command (including package:discover) fatals with "Please provide a valid
# cache path" when storage/framework/views does not exist yet.
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/testing storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi

RUN npm run build

EXPOSE 8080

# Single-service Railway deploy: migrations run before the caches so a fresh
# database never serves 500s on missing tables; supervisord then keeps the HTTP
# server, a plain queue worker and the scheduler alive in the same container
# (see docker/supervisord.conf). The worker needs a reachable queue backend
# (QUEUE_CONNECTION=redis + REDIS_URL/REDIS_HOST) - without it, it exits and
# supervisord restarts it in a crash loop. The scheduler is what actually runs
# backup:run, backup:clean, backups:purge-quarantine, backup:monitor and
# activitylog:clean; without it those tasks never execute on Railway.
CMD ["sh", "-c", "php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && exec supervisord -c docker/supervisord.conf"]
