# PHP 8.5.12 includes fixes for the critical October 2026 PHP CVEs.
FROM php:8.5.12-apache

# 1. Retry transient Debian package download failures and build dependencies
RUN set -eux; \
    echo 'Acquire::Retries "3";' > /etc/apt/apt.conf.d/80-retries; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
    ca-certificates \
    unzip \
    zip; \
    rm -rf /var/lib/apt/lists/*

# 2. Install MySQL PDO extension dynamically scaling across available CPU threads
RUN set -eux; \
    docker-php-ext-install -j$(nproc) pdo_mysql; \
    a2enmod rewrite headers

# 3. Use PHP's production defaults
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 4. Production OPcache settings
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini

# 5. Prevent Composer warnings when running commands inside the container
ENV COMPOSER_ALLOW_SUPERUSER=1
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# 6. Apache virtual host
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# 7. Production Permissions (Ensures Apache runs securely as www-data)
RUN mkdir -p /var/www/html \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# NOTE FOR WINDOWS/GIT BASH LOCAL DEVELOPMENT ONLY:
# Do not hardcode ENV APACHE_RUN_USER=root here for production.
# Instead, override environment variables locally using your docker-compose.yml file.
