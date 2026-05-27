FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    libmemcached-dev \
    zlib1g-dev \
    libssl-dev \
    libldap2-dev \
    unzip \
    git \
    && pecl install memcached \
    && docker-php-ext-enable memcached \
    && docker-php-ext-install pdo_mysql ldap \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/fbctf
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader || true

COPY src/ src/
COPY extra/settings.ini.docker settings.ini
COPY extra/hash.php extra/hash.php

RUN mkdir -p attachments attachments/deleted src/data/customlogos \
    && chown -R www-data:www-data attachments src/data/customlogos
