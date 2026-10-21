#!/bin/bash
set -e

: "${PORT:=10000}"

# Point Apache's listen directive + default vhost at Render's assigned port
sed -ri "s/Listen [0-9]+/Listen ${PORT}/g" /etc/apache2/ports.conf
sed -ri "s/:80/:${PORT}/g" /etc/apache2/sites-available/000-default.conf

# Run database bootstrap and schema migration if database is configured
if [ -n "$DATABASE_URL" ] || [ -n "$DB_HOST" ]; then
    echo "==> Running BUK EIMS database bootstrap..."
    php /var/www/html/migrate.php || echo "[WARN] Migration exited with error; proceeding to start web server."
fi

exec "$@"
