# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.5.12

# ==========================================
# STAGE 1: base - runtime + extensions + non-restrictive config
# NOTE: the php2shell hardening ini is deliberately NOT copied here.
#       open_basedir / disable_functions would break Composer in the
#       vendor stage. Hardening is applied in the production stage only.
# ==========================================
FROM php:${PHP_VERSION}-apache AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN set -eux; \
    chown root:root /var/www/html; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl; \
    rm -rf /var/lib/apt/lists/*; \
    install-php-extensions pdo_mysql zip; \
    rm -f /usr/local/bin/install-php-extensions; \
    a2enmod rewrite headers remoteip; \
    a2dismod -f autoindex; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    printf '%s\n' \
    'ServerName localhost' \
    'ServerTokens Prod' \
    'ServerSignature Off' \
    'TraceEnable Off' \
    > /etc/apache2/conf-available/zz-security.conf; \
    a2enconf zz-security

COPY docker/php/opcache.ini   $PHP_INI_DIR/conf.d/90-opcache.ini
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# ==========================================
# STAGE 2: vendor - Composer, git and unzip live here only (unhardened)
# ==========================================
FROM base AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_CACHE_DIR=/tmp/composer

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    chown root:root /var/www/html; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip git; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .

RUN --mount=type=cache,target=/tmp/composer \
    composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && composer check-platform-reqs --no-dev

# ==========================================
# STAGE 3: production - hardened, no Composer, no git, code is read-only
# ==========================================
FROM base AS production

LABEL org.opencontainers.image.title="app" \
    org.opencontainers.image.vendor="Mtechnology" \
    org.opencontainers.image.authors="rohit.milan3@gmail.com"

# Hardening applies ONLY to the runtime image
COPY docker/php/security_php2shell.ini $PHP_INI_DIR/conf.d/99-security-hardening.ini

WORKDIR /var/www/html

# Files stay owned by root: a compromised www-data cannot modify application code
COPY --from=vendor /app /var/www/html

RUN set -eux; \
    chown root:root /var/www/html; \
    find /var/www/html -type d -exec chmod 755 {} +; \
    find /var/www/html -type f -exec chmod 644 {} +; \
    mkdir -p /var/www/html/storage/logs /var/run/apache2 /var/lock/apache2; \
    chown -R www-data:www-data /var/www/html/storage /var/run/apache2 /var/lock/apache2; \
    chmod -R 750 /var/www/html/storage; \
    sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf; \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=20s --timeout=4s --start-period=15s --retries=3 \
    CMD curl -fsS http://localhost:8080/healthz || exit 1