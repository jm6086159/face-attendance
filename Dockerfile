# -------------------------------
# Laravel + SQLite with Nginx + PHP-FPM
# -------------------------------

# Base image
FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    git unzip libpng-dev libonig-dev libxml2-dev zip curl libzip-dev libsqlite3-dev \
    nginx supervisor \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy app files
COPY . .

# Ensure database folder and SQLite file exist
RUN mkdir -p /var/www/database \
    && touch /var/www/database/database.sqlite

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Set permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/database

# Copy Nginx config
COPY default.conf /etc/nginx/conf.d/default.conf

# Supervisor config to run PHP-FPM + Nginx
RUN mkdir -p /var/log/supervisor

COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose HTTP port
EXPOSE 80

# Start Supervisor
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
