#!/bin/bash
set -e

: "${PORT:=10000}"

# Point Apache's listen directive + default vhost at Render's assigned port
sed -ri "s/Listen [0-9]+/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

exec "$@"
