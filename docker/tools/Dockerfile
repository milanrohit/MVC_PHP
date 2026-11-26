FROM php:8.6-cli

# Install the install-php-extensions helper script
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*; \
    install-php-extensions mbstring xml dom pdo_mysql zip pcntl; \
    git config --system --add safe.directory /app; \
    addgroup --gid 1000 app; \
    adduser --uid 1000 --gid 1000 --disabled-password --gecos '' app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
RUN chown -R app:app /app

USER app
