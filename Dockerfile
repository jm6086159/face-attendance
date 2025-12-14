# Single container: nginx + php-fpm + sqlite (Render compatible)
FROM php:8.2-fpm

# -----------------------------
# System dependencies
# -----------------------------
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    nginx supervisor sqlite3 libsqlite3-dev gettext-base \
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
    storage/app/public \
    bootstrap/cache \
    database \
 && touch database/database.sqlite \
 && chown -R www-data:www-data /var/www \
 && chmod -R 775 storage bootstrap/cache database \
 && chmod 664 database/database.sqlite

# -----------------------------
# PHP dependencies
# -----------------------------
RUN composer install --no-dev --optimize-autoloader --no-interaction

# -----------------------------
# Config files
# -----------------------------
COPY docker/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# -----------------------------
# Entrypoint script
# -----------------------------
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# -----------------------------
# Laravel prep (safe on Render)
# -----------------------------
RUN php artisan storage:link || true \
 && php artisan config:clear || true \
 && php artisan cache:clear || true \
 && php artisan route:clear || true \
 && php artisan view:clear || true

# Set PORT default (Render will override)
ENV PORT=8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/conf.d/supervisord.conf"]