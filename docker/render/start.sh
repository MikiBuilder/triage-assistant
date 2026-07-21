#!/bin/sh
set -e
export PORT=${PORT:-10000}
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf
php bin/console cache:clear --no-warmup || true
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf