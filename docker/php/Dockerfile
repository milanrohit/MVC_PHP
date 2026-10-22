
# PHP 8.5.12 includes fixes for the critical October 2026 PHP CVEs.
FROM php:8.5.12-apache

# Retry transient Debian package download failures.
RUN set -eux; \
    echo 'Acquire::Retries "3";' > /etc/apt/apt.conf.d/80-retries; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
    ca-certificates \
    unzip \
    zip; \
    rm -rf /var/lib/apt/lists/*

# Install only the MySQL PDO extension.
# OPcache is provided by the official PHP image.
RUN set -eux; \
    docker-php-ext-install -j2 pdo_mysql; \
    a2enmod rewrite headers

# Use PHP's production defaults.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Production OPcache settings.
COPY docker/php/opcache.ini \
    /usr/local/etc/php/conf.d/99-opcache.ini

# Composer executable.
COPY --from=composer:2 /usr/bin/composer \
    /usr/local/bin/composer

# Apache virtual host.
COPY docker/apache/vhost.conf \
    /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html
