# syntax=docker/dockerfile:1

# Single-stage Railway image. The Vite build MUST run here (not in a
# node-only stage): @laravel/vite-plugin-wayfinder shells out to
# `php artisan wayfinder:generate` on every build, so PHP + vendor/ have to
# exist before `npm run build`. (A node:24 stage fails with "php: not found".)
FROM ubuntu:24.04 AS app

ARG PHP_VERSION=8.5
ARG VITE_REVERB_APP_KEY
ARG VITE_REVERB_HOST
ARG VITE_REVERB_PORT
ARG VITE_REVERB_SCHEME
ENV DEBIAN_FRONTEND=noninteractive

# PHP 8.5 via ondrej PPA + Node 24 via nodesource.
RUN mkdir -p /etc/apt/keyrings \
    && apt-get update && apt-get install -y --no-install-recommends \
        gnupg curl ca-certificates git unzip \
    && curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' \
        | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu noble main" \
        > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
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
        nodejs \
        supervisor \
        ffmpeg \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && apt-get -y autoremove && apt-get clean && rm -rf /var/lib/apt/lists/*

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
# database never serves 500s on missing tables; supervisord then keeps both the
# HTTP server and a plain queue worker alive in the same container
# (see docker/supervisord.conf). The worker needs a reachable queue backend
# (QUEUE_CONNECTION=redis + REDIS_URL/REDIS_HOST) - without it, it exits and
# supervisord restarts it in a crash loop.
CMD ["sh", "-c", "php artisan migrate --force && php artisan config:cache && php artisan route:cache && php artisan view:cache && exec supervisord -c docker/supervisord.conf"]
