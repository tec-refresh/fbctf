#!/bin/bash
set -e

if [[ -e /root/tmp/certbot.sh ]]; then
    /bin/bash /root/tmp/certbot.sh
fi

chown -R www-data:www-data /var/www/fbctf

service php8.3-fpm restart
service nginx restart
service memcached restart

while true; do
    sleep 5
    service php8.3-fpm status
    service nginx status
    service memcached status
done
