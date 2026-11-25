FROM php:8.6-cli

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends git unzip; \
    rm -rf /var/lib/apt/lists/*; \
    docker-php-ext-install pcntl; \
    addgroup --gid 1000 app; \
    adduser --uid 1000 --gid 1000 --disabled-password --gecos '' app

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

USER app
