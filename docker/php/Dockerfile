FROM php:8.3-fpm-bullseye
 
RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
&& rm -rf /var/lib/apt/lists/*
 
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
&& docker-php-ext-install -j$(nproc) \
        intl \
        opcache \
        pdo \
        pdo_pgsql \
        zip \
        gd

RUN pecl install redis \
&& docker-php-ext-enable redis
 
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN git config --global --add safe.directory '*'

WORKDIR /var/www/toa-api

EXPOSE 9000
CMD ["php-fpm"]