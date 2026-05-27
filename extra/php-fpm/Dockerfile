FROM node:20 AS frontend

WORKDIR /build
COPY package.json ./
RUN npm install

COPY Gruntfile.js ./
COPY src/static/ src/static/
RUN npx grunt build

FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libmemcached-dev \
    zlib1g-dev \
    libssl-dev \
    libldap2-dev \
    libicu-dev \
    unzip \
    git \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && docker-php-ext-install pdo_mysql ldap intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN echo 'display_errors = Off' > /usr/local/etc/php/conf.d/errors.ini \
    && echo 'log_errors = On' >> /usr/local/etc/php/conf.d/errors.ini \
    && echo 'error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT' >> /usr/local/etc/php/conf.d/errors.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/fbctf
COPY composer.json composer.lock* ./
COPY src/ src/
COPY database/ database/
COPY extra/settings.ini.docker settings.ini
COPY extra/hash.php extra/hash.php

COPY --from=frontend /build/src/static/css/fb-ctf.css src/static/css/fb-ctf.css
COPY --from=frontend /build/src/static/dist/ src/static/dist/

RUN composer install --no-dev --optimize-autoloader

RUN mkdir -p attachments attachments/deleted src/data/customlogos \
    && chown -R www-data:www-data attachments src/data/customlogos
