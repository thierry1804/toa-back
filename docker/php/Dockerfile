FROM php:8.3-fpm-bullseye

ARG PHP_PATCHED_VERSION=8.3.36

# Bullseye a atteint sa fin de vie (depots deb.debian.org en 404) ->
# bascule sur l'archive figee pour cette etape de build.
RUN printf 'deb http://archive.debian.org/debian bullseye main\ndeb http://archive.debian.org/debian-security bullseye-security main\n' > /etc/apt/sources.list \
&& echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid-until

RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    git \
    unzip \
    wget \
    xz-utils \
    pkg-config \
    autoconf \
    bison \
    re2c \
    build-essential \
    libxml2-dev \
    libsqlite3-dev \
    libssl-dev \
    zlib1g-dev \
    libcurl4-openssl-dev \
    libreadline-dev \
    libsodium-dev \
    libargon2-dev \
    libonig-dev \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
&& rm -rf /var/lib/apt/lists/*

# CVE-2026-109618 / CVE-2026-109619 ("PHP2Shell"), RCE critique corrigee par le
# PHP Group le 21/10/2026 dans la 8.3.36. L'image officielle php:8.3-fpm-bullseye
# n'etait pas encore republiee avec le correctif au moment de ce build (verifie
# le 21/10/2026 : tag toujours sur 8.3.24) -> on recompile le coeur PHP depuis
# les sources officielles avec les memes flags de configure que l'image de base,
# plus les extensions normalement ajoutees apres coup (intl, opcache, pdo_pgsql,
# zip, gd) pour n'avoir qu'une seule compilation.
RUN cd /usr/src \
    && wget -q https://www.php.net/distributions/php-${PHP_PATCHED_VERSION}.tar.xz \
    && tar xf php-${PHP_PATCHED_VERSION}.tar.xz \
    && cd php-${PHP_PATCHED_VERSION} \
    && ./configure \
        --build=x86_64-linux-gnu \
        --with-config-file-path=/usr/local/etc/php \
        --with-config-file-scan-dir=/usr/local/etc/php/conf.d \
        --enable-option-checking=fatal \
        --with-mhash \
        --with-pic \
        --enable-mbstring \
        --enable-mysqlnd \
        --with-password-argon2 \
        --with-sodium=shared \
        --with-pdo-sqlite=/usr \
        --with-sqlite3=/usr \
        --with-curl \
        --with-iconv \
        --with-openssl \
        --with-readline \
        --with-zlib \
        --disable-phpdbg \
        --with-pear \
        --with-libdir=lib/x86_64-linux-gnu \
        --disable-cgi \
        --enable-fpm \
        --with-fpm-user=www-data \
        --with-fpm-group=www-data \
        --enable-intl \
        --enable-opcache \
        --with-pdo-pgsql \
        --with-zip \
        --enable-gd --with-jpeg --with-freetype \
    && make -j"$(nproc)" \
    && make install \
    && cd / && rm -rf /usr/src/php-${PHP_PATCHED_VERSION} /usr/src/php-${PHP_PATCHED_VERSION}.tar.xz

RUN pecl install redis \
&& docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
COPY uploads.ini /usr/local/etc/php/conf.d/uploads.ini

RUN git config --global --add safe.directory '*'

WORKDIR /var/www/toa-api

EXPOSE 9000
CMD ["php-fpm"]
