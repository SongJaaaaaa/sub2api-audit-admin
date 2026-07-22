#!/bin/sh
set -eu

mkdir -p \
  /var/www/html/bootstrap/cache \
  /var/www/html/database \
  /var/www/html/storage/framework/cache/data \
  /var/www/html/storage/framework/sessions \
  /var/www/html/storage/framework/views \
  /var/www/html/storage/logs

chown -R www-data:www-data \
  /var/www/html/bootstrap/cache \
  /var/www/html/database \
  /var/www/html/storage

find /var/www/html/database -maxdepth 1 -type f -name '*.sqlite' -exec chmod 660 {} +
if [ -f /var/www/html/storage/app.key ]; then
  chmod 600 /var/www/html/storage/app.key
fi

exec "$@"
