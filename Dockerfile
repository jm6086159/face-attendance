# Single container: nginx + php-fpm + sqlite (Render compatible)
FROM php:8.2-fpm

# -----------------------------
# System dependencies
# -----------------------------
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    nginx supervisor sqlite3 libsqlite3-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev libzip-dev \
    zip unzip git curl \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    gd pdo pdo_sqlite mbstring bcmath zip xml \
 && rm -rf /var/lib/apt/lists/*

# -----------------------------
# Composer
# -----------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# -----------------------------
# App directory
# -----------------------------
WORKDIR /var/www

COPY . .

# -----------------------------
# Laravel folders + SQLite
# -----------------------------
RUN mkdir -p \
    storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache \
    database \
 && touch database/database.sqlite \
 && chown -R www-data:www-data /var/www \
 && chmod -R 775 storage bootstrap/cache database \
 && chmod 666 database/database.sqlite

# -----------------------------
# PHP dependencies
# -----------------------------
RUN composer install --no-dev --optimize-autoloader --no-interaction

# -----------------------------
# Config files
# -----------------------------
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# -----------------------------
# Laravel prep (safe on Render)
# -----------------------------
RUN php artisan key:generate --force || true \
 && php artisan config:clear || true \
 && php artisan cache:clear || true \
 && php artisan route:clear || true \
 && php artisan view:clear || true

# ❌ DO NOT expose 80 (Render injects $PORT)
# ❌ DO NOT add daemon off to nginx.conf

CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/conf.d/supervisord.conf"]
