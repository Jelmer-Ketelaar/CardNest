FROM php:8.4-cli-alpine

RUN apk add --no-cache git icu-dev libzip-dev postgresql-dev unzip \
    && docker-php-ext-install intl opcache pdo_pgsql zip \
    && apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .phpize-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
