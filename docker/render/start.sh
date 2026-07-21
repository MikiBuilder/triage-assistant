#!/bin/sh
set -e
export PORT=${PORT:-10000}
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf
mkdir -p var/cache var/log
php bin/console cache:clear --no-warmup || true
chown -R www-data:www-data var
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf