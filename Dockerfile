# Laravel + Nginx + PHP-FPM + SQLite (SINGLE CONTAINER)
FROM php:8.2-fpm

# Install system packages
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    git \
    unzip \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libsqlite3-dev \
    zip \
    && docker-php-ext-install pdo pdo_sqlite mbstring bcmath gd zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy Laravel source
COPY . .

# Create required directories + SQLite DB
RUN mkdir -p \
    storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    database \
    bootstrap/cache \
    && touch database/database.sqlite

# Permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache database

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy Nginx + Supervisor configs
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Expose HTTP
EXPOSE 80

# Start everything
CMD ["/usr/bin/supervisord", "-n"]
