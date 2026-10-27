# ==========================================
# STAGE 1: Build & Source Code Optimization
# ==========================================
FROM php:8.5.12-apache AS development

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_ENV=production

RUN set -eux; \
    echo 'Acquire::Retries "3";' > /etc/apt/apt.conf.d/80-retries; \
    apt-get update; \
    apt-get install -y --no-install-recommends unzip zip git; \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./

# Production strictness: optimize class maps, skip development packages entirely
RUN composer install --no-dev --optimize-autoloader --no-scripts --prefer-dist

# ==========================================
# STAGE 2: Future-Proof Production Runtime
# ==========================================
FROM php:8.5.12-apache AS production

LABEL maintainer="infrastructure-team@company.com" \
    tier="api-gateway" \
    orchestration="kubernetes"

# 1. Base hardening layers
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# FIX PART A: Permanently copy Composer into the final production runtime image too
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN set -eux; \
    echo 'Acquire::Retries "3";' > /etc/apt/apt.conf.d/80-retries; \
    apt-get update; \
    # curl is required purely for the secure runtime healthcheck engine
    apt-get install -y --no-install-recommends ca-certificates curl; \
    rm -rf /var/lib/apt/lists/*

# 2. Complete Enterprise Database & Speed Extension Suite
RUN set -eux; \
    IPE_GD_WITHOUT_AVIF=1 install-php-extensions \
    pdo_mysql \
    pdo_pgsql \
    pdo_sqlite \
    pdo_sqlsrv \
    mongodb \
    redis \
    opcache

# 3. Apply hardened production configurations
RUN a2enmod rewrite headers remoteip \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Advanced tuning files mapped from your CI/CD repository
# NOTE: Ensure these local files actually exist inside your project directory!
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini
COPY docker/apache/vhost.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# 4. Pull safe runtime files from the development stage
COPY --from=development /app/vendor /var/www/html/vendor
COPY . /var/www/html/

# 5. Read-Only Sandbox Execution Security Matrix
# FIX PART B: Fixed the permissions so Apache (www-data) can read files safely while keeping security boundaries
RUN set -eux; \
    chown -R www-data:www-data /var/www/html; \
    chmod -R 755 /var/www/html; \
    # Dynamically handle application-specific writable storage paths safely
    if [ -d "/var/www/html/storage" ]; then \
    chown -R www-data:www-data /var/www/html/storage; \
    chmod -R 775 /var/www/html/storage; \
    fi

# 6. Continuous Infrastructure Self-Healing Liveness Probe
HEALTHCHECK --interval=20s --timeout=4s --start-period=15s --retries=3 \
    CMD curl -f http://localhost/healthz || exit 1

# FIX PART C: Run Apache as root globally (standard for Docker) so it can bind to port 80, 
# Apache will internally drop its child workers down to www-data safely automatically.
USER root
