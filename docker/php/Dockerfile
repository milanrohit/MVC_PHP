# Keep your exact PHP 8.4 Apache variant
FROM php:8.4-apache

# 1. Group core dependencies, optimize apt cache, and install persistent utilities
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    # zip is explicitly required by Composer to extract packages efficiently
    zip \ 
    && docker-php-ext-install pdo pdo_mysql opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# 2. Inject optimal production PHP configuration (Includes 2026 security & OPcache tuning)
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

# 3. Pull modern stable Composer dependency
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 4. Standard virtual host configuration
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

# 5. Set up secure working directory and manage group ownership permissions
WORKDIR /var/www/html

# Ensure the web server (www-data) owns the runtime directory for seamless asset compilation
RUN chown -R www-data:www-data /var/www/html
