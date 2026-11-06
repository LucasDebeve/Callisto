# syntax=docker/dockerfile:1.7
# Images de PRODUCTION uniquement : application (PHP-FPM) et Caddy servant les fichiers publics compilés.
# En développement, l'application tourne sur le poste (composer dev) ; seuls les services sont dans Docker.
# Versions épinglées (S19), mises à jour proposées par Renovate.

ARG PHP_VERSION=8.3.14
ARG COMPOSER_VERSION=2.8.12
ARG CADDY_VERSION=2.8.4

FROM composer:${COMPOSER_VERSION} AS composer

# ---------------------------------------------------------------- Base PHP
FROM php:${PHP_VERSION}-fpm-alpine AS php-base
RUN apk add --no-cache icu-libs libpq libzip fcgi \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS icu-dev libpq-dev libzip-dev linux-headers \
 && docker-php-ext-install -j"$(nproc)" intl pdo_pgsql opcache zip \
 && apk del .build-deps
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-callisto.ini
COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zz-callisto.conf
COPY --from=composer /usr/bin/composer /usr/bin/composer
WORKDIR /app

# ---------------------------------------------------------------- Construction prod
FROM php-base AS build
ENV APP_ENV=prod COMPOSER_ALLOW_SUPERUSER=1
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress
COPY . .
RUN composer dump-autoload --classmap-authoritative --no-dev \
 && APP_SECRET=build php bin/console tailwind:build --minify \
 && APP_SECRET=build php bin/console asset-map:compile \
 && APP_SECRET=build php bin/console cache:warmup

# ---------------------------------------------------------------- Production (sans root)
FROM php-base AS php-prod
ENV APP_ENV=prod APP_DEBUG=0
COPY --from=build --chown=www-data:www-data /app/bin /app/bin
COPY --from=build --chown=www-data:www-data /app/config /app/config
COPY --from=build --chown=www-data:www-data /app/migrations /app/migrations
COPY --from=build --chown=www-data:www-data /app/public /app/public
COPY --from=build --chown=www-data:www-data /app/src /app/src
COPY --from=build --chown=www-data:www-data /app/templates /app/templates
COPY --from=build --chown=www-data:www-data /app/vendor /app/vendor
COPY --from=build --chown=www-data:www-data /app/var/cache /app/var/cache
COPY --from=build --chown=www-data:www-data /app/composer.json /app/.env /app/importmap.php /app/
RUN mkdir -p /app/var/log /data/content-files && chown -R www-data:www-data /app/var /data
USER www-data
HEALTHCHECK --interval=30s --timeout=5s CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1
CMD ["php-fpm", "--nodaemonize"]

# ---------------------------------------------------------------- Caddy
FROM caddy:${CADDY_VERSION}-alpine AS caddy-prod
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=build /app/public /app/public
