ARG PHP_VERSION=8.6

# ==========================================
# STAGE 1: base - runtime + extensions + config (shared by later stages)
# ==========================================
FROM php:${PHP_VERSION}-apache AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

# Install ONLY the extensions the app uses. Add pdo_sqlsrv here only if you really need MSSQL.
# OPcache is built into PHP 8.6, so it only needs enabling via ini (no install step).
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends ca-certificates curl; \
    rm -rf /var/lib/apt/lists/*; \
    install-php-extensions pdo_mysql; \
    a2enmod rewrite headers remoteip; \
    a2dismod -f autoindex; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/security.ini      $PHP_INI_DIR/conf.d/90-security.ini
COPY docker/php/opcache.ini       $PHP_INI_DIR/conf.d/99-opcache.ini
COPY docker/apache/security.conf  /etc/apache2/conf-available/zz-security.conf
COPY docker/apache/vhost.conf     /etc/apache2/sites-available/000-default.conf

RUN a2enconf zz-security

# ==========================================
# STAGE 2: vendor - Composer, git and unzip live here only
# ==========================================
FROM base AS vendor

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_CACHE_DIR=/tmp/composer

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip git; \
    rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY composer.json composer.lock ./
RUN --mount=type=cache,target=/tmp/composer \
    composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .

# Build the autoloader AFTER the source is present, otherwise app classes are missing from the classmap
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && composer check-platform-reqs --no-dev

# ==========================================
# STAGE 3: production - no Composer, no git, code is read-only
# ==========================================
FROM base AS production

ENV APACHE_RUN_DIR=/tmp/apache2 \
    APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_PID_FILE=/tmp/apache2/apache2.pid \
    APACHE_LOCK_DIR=/tmp/apache2 \
    APACHE_LOG_DIR=/tmp/apache2

LABEL org.opencontainers.image.title="app" \
    org.opencontainers.image.vendor="company" \
    org.opencontainers.image.authors="infrastructure-team@company.com"

WORKDIR /var/www/html

# Files stay owned by root: a compromised www-data cannot modify application code
COPY --from=vendor /app /var/www/html

RUN set -eux; \
    sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf; \
    sed -i 's/<VirtualHost \*:80>/<VirtualHost *:8080>/' /etc/apache2/sites-available/000-default.conf; \
    find /var/www/html -type d -exec chmod 755 {} +; \
    find /var/www/html -type f -exec chmod 644 {} +; \
    mkdir -p /var/www/html/storage; \
    chown -R www-data:www-data /var/www/html/storage; \
    chmod -R 750 /var/www/html/storage; \
    mkdir -p "$APACHE_RUN_DIR"; \
    chown www-data:www-data "$APACHE_RUN_DIR"


USER 33:33

EXPOSE 8080

HEALTHCHECK --interval=20s --timeout=4s --start-period=15s --retries=3 \
    CMD ["sh", "-c", "curl -fsS http://localhost:8080/healthz || exit 1"]

# Apache and PHP run as www-data (uid 33); port 8080 does not require root privileges.
