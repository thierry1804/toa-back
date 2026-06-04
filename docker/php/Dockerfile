FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git curl unzip libpq-dev libzip-dev \
    && docker-php-ext-install pdo pdo_pgsql zip opcache

# Redis extension (for Messenger)
RUN pecl install redis && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

RUN git config --global --add safe.directory '*'

WORKDIR /var/www/toa-api