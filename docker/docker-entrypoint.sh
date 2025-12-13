#!/bin/sh
set -e

cd /var/www

echo "[entrypoint] starting..."

# If Render gives DB path via env, honor it by writing into .env if exists
update_env_var() {
  file=$1
  key=$2
  value=$3
  if [ -z "$value" ]; then
    return
  fi
  if grep -q "^${key}=" "$file" 2>/dev/null; then
    sed -i "s|^${key}=.*|${key}=${value}|" "$file"
  else
    echo "${key}=${value}" >> "$file"
  fi
}

# Create .env from example if missing
if [ ! -f /var/www/.env ] && [ -f /var/www/.env.example ]; then
  cp /var/www/.env.example /var/www/.env
  echo "[entrypoint] copied .env.example -> .env"
fi

# If Render provided DB_DATABASE via environment variable, ensure .env has it
if [ -f /var/www/.env ]; then
  update_env_var /var/www/.env DB_CONNECTION "${DB_CONNECTION:-sqlite}"
  # prefer environment variable provided DB_DATABASE, otherwise keep existing or default
  update_env_var /var/www/.env DB_DATABASE "${DB_DATABASE:-/var/www/database/database.sqlite}"
fi

# Ensure DB file exists and is writable
mkdir -p /var/www/database
touch /var/www/database/database.sqlite
chmod 666 /var/www/database/database.sqlite || true

# Ensure storage and bootstrap cache perms
mkdir -p storage/logs storage/framework cache bootstrap/cache
chown -R www-data:www-data /var/www || true
chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/database || true

# If APP_KEY missing, generate (will respect already present APP_KEY or env var)
if [ -f /var/www/.env ]; then
  if ! grep -q '^APP_KEY=' /var/www/.env || [ -z "$(grep '^APP_KEY=' /var/www/.env | cut -d'=' -f2-)" ]; then
    echo "[entrypoint] APP_KEY missing — generating..."
    php artisan key:generate --force || echo "[entrypoint] key:generate failed"
  else
    echo "[entrypoint] APP_KEY exists"
  fi
fi

# Clear caches (safe)
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# Optionally run migrations at runtime
# To enable, set RUN_MIGRATIONS=true in Render's Environment variables
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "[entrypoint] RUN_MIGRATIONS=true — running migrations..."
  php artisan migrate --force || echo "[entrypoint] migrate failed (continuing)"
else
  echo "[entrypoint] RUN_MIGRATIONS not enabled; skip migrations"
fi

echo "[entrypoint] environment ready — starting supervisord"
exec "$@"
