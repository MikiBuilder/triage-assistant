#!/bin/sh
set -e
export PORT=${PORT:-10000}
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf