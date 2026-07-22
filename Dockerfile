# syntax=docker/dockerfile:1

# -----------------------------------------------------------------------------
# Stage: vendor — PHP dependencies (production set; no scripts)
# -----------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --prefer-dist \
    --ignore-platform-reqs \
    --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev --ignore-platform-reqs

# -----------------------------------------------------------------------------
# Stage: assets — frontend build (Vite)
# -----------------------------------------------------------------------------
FROM node:20-alpine AS assets

WORKDIR /app

COPY package.json package-lock.json* ./

RUN npm ci

COPY . .

COPY --from=vendor /app/vendor ./vendor

RUN npm run build

# -----------------------------------------------------------------------------
# Stage: base — FrankenPHP + extensions + app code
# -----------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php8.3 AS base

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        ca-certificates \
        default-mysql-client \
        supervisor \
        gnupg \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && install-php-extensions \
        pdo_mysql \
        mysqli \
        bcmath \
        gd \
        intl \
        zip \
        pcntl \
        exif \
        opcache \
        redis \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# MariaDB client defaults to SSL; disable for Docker-network MySQL (no real TLS).
RUN mkdir -p /etc/mysql/conf.d \
    && printf '%s\n' '[client]' 'ssl=0' > /etc/mysql/conf.d/retailpulse-no-ssl.cnf

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-retailpulse.ini
COPY docker/supervisor/supervisord.local.conf /etc/supervisor/retailpulse/supervisord.local.conf
COPY docker/supervisor/supervisord.production.conf /etc/supervisor/retailpulse/supervisord.production.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

EXPOSE 8000 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# -----------------------------------------------------------------------------
# Stage: local — development (no Octane / Horizon processes)
# -----------------------------------------------------------------------------
FROM base AS local

ENV APP_ENV=local

COPY docker/php/php.local.ini /usr/local/etc/php/conf.d/99-retailpulse-local.ini

RUN composer install \
    --no-interaction \
    --prefer-dist \
    --ignore-platform-reqs

CMD ["local"]

# -----------------------------------------------------------------------------
# Stage: production — Octane (FrankenPHP) + Horizon
# -----------------------------------------------------------------------------
FROM base AS production

ENV APP_ENV=production

# Packages are already in composer.json/lock; re-require keeps the image
# aligned if lock drifts, then install a clean production tree.
RUN composer require laravel/octane laravel/horizon --no-interaction --no-scripts --update-with-all-dependencies \
    && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist \
    && cp .env.example .env \
    && php artisan key:generate --force --no-interaction \
    && php artisan octane:install --server=frankenphp --no-interaction \
    && php artisan horizon:publish --no-interaction \
    && rm -f .env

CMD ["production"]
