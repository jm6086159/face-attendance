# Laravel + Nginx + PHP-FPM + SQLite (SINGLE CONTAINER)
FROM php:8.2-fpm

# Install system packages with GD extension support
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
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_sqlite mbstring bcmath zip \
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
    /var/log/nginx \
    /var/lib/nginx \
    && touch database/database.sqlite

# Permissions - FIXED ORDER
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache \
    && chmod 666 database/database.sqlite

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Run database migrations
RUN php artisan migrate --force

# Clear caches
RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear

# Copy Nginx + Supervisor configs
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Create Nginx config symlink (force overwrite)
RUN mkdir -p /etc/nginx/sites-enabled \
    && rm -f /etc/nginx/sites-enabled/default \
    && ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# Create storage link
RUN php artisan storage:link

# Test Nginx configuration
RUN nginx -t

# Expose HTTP
EXPOSE 80

# Start everything with Supervisor
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]