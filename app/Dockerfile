FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

FROM php:8.3-fpm-alpine

RUN apk add --no-cache libzip-dev oniguruma-dev \
    && docker-php-ext-install pdo_mysql bcmath opcache zip \
    && apk del libzip-dev oniguruma-dev

WORKDIR /var/www/html

COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

CMD ["php-fpm"]
