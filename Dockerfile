# -------------------------------
# Laravel + SQLite Dockerfile
# -------------------------------

# Use PHP 8.2 FPM base image
FROM php:8.2-fpm

# Install system dependencies and SQLite dev package
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    git unzip libpng-dev libonig-dev libxml2-dev zip curl libzip-dev libsqlite3-dev \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring exif pcntl bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy project files
COPY . .

# Ensure database folder and SQLite file exist
RUN mkdir -p /var/www/database \
    && touch /var/www/database/database.sqlite

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Set folder permissions for Laravel
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/database

# Expose PHP-FPM port
EXPOSE 9000

# Start PHP-FPM
CMD ["php-fpm"]
