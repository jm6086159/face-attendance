#!/bin/sh
set -e

cd /var/www

echo "[entrypoint] Starting setup..."

# Process nginx template with PORT
export PORT=${PORT:-8080}
echo "[entrypoint] PORT=$PORT"
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf
echo "[entrypoint] nginx config generated"

# Ensure directories exist
mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views storage/app/public bootstrap/cache database

# Ensure SQLite DB exists
if [ ! -f /var/www/database/database.sqlite ]; then
    echo "[entrypoint] Creating new SQLite database..."
    touch /var/www/database/database.sqlite
else
    echo "[entrypoint] SQLite database already exists"
fi

# Fix permissions
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/database
chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/database
chmod 664 /var/www/database/database.sqlite

echo "[entrypoint] Permissions set"

# Laravel cache clearing
php artisan config:clear || echo "[entrypoint] config:clear failed"
php artisan cache:clear || echo "[entrypoint] cache:clear failed"
php artisan route:clear || echo "[entrypoint] route:clear failed"
php artisan view:clear || echo "[entrypoint] view:clear failed"

# Run migrations if enabled
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "[entrypoint] Running migrations..."
    php artisan migrate --force || echo "[entrypoint] migrate failed"
else
    echo "[entrypoint] Skipping migrations"
fi

echo "[entrypoint] Setup complete - starting supervisord"
exec "$@"
