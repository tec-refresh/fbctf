FROM nginx:stable

RUN mkdir -p /etc/nginx/certs \
    && openssl req -nodes -newkey rsa:2048 \
       -keyout /etc/nginx/certs/dev.key \
       -out /etc/nginx/certs/dev.csr \
       -subj "/O=Facebook CTF" \
    && openssl x509 -req -days 365 \
       -in /etc/nginx/certs/dev.csr \
       -signkey /etc/nginx/certs/dev.key \
       -out /etc/nginx/certs/dev.crt \
    && openssl dhparam -out /etc/nginx/certs/dhparam.pem 2048

COPY extra/nginx/nginx.conf /etc/nginx/conf.d/fbctf.conf.template
COPY src/ /var/www/fbctf/src/

RUN rm -f /etc/nginx/conf.d/default.conf \
    && sed -e 's|CTFPATH|/var/www/fbctf/src|g' \
           -e 's|CER_FILE|/etc/nginx/certs/dev.crt|g' \
           -e 's|KEY_FILE|/etc/nginx/certs/dev.key|g' \
           -e 's|DHPARAM_FILE|/etc/nginx/certs/dhparam.pem|g' \
           -e 's|PHPFPMSERVER|php-fpm|g' \
           /etc/nginx/conf.d/fbctf.conf.template > /etc/nginx/conf.d/fbctf.conf
