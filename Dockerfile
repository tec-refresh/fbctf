FROM php:8.3-fpm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    memcached \
    supervisor \
    libmemcached-dev \
    zlib1g-dev \
    libssl-dev \
    libldap2-dev \
    openssl \
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
COPY extra/nginx.conf /etc/nginx/sites-available/fbctf.conf
COPY extra/supervisord.conf /etc/supervisor/conf.d/fbctf.conf
COPY extra/settings.ini.example settings.ini

RUN mkdir -p /etc/nginx/certs attachments attachments/deleted src/data/customlogos \
    && openssl req -nodes -newkey rsa:2048 \
       -keyout /etc/nginx/certs/dev.key \
       -out /etc/nginx/certs/dev.csr \
       -subj "/O=Facebook CTF" \
    && openssl x509 -req -days 365 \
       -in /etc/nginx/certs/dev.csr \
       -signkey /etc/nginx/certs/dev.key \
       -out /etc/nginx/certs/dev.crt \
    && openssl dhparam -out /etc/nginx/certs/dhparam.pem 2048 \
    && ln -sf /etc/nginx/sites-available/fbctf.conf /etc/nginx/sites-enabled/fbctf.conf \
    && rm -f /etc/nginx/sites-enabled/default \
    && chown -R www-data:www-data /var/www/fbctf

EXPOSE 80 443

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/fbctf.conf"]
