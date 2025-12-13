# Laravel + Nginx + PHP-FPM + SQLite (Single Container)
FROM php:8.2-fpm

# Install system packages with all required dependencies
RUN apt-get update && apt-get install -y \
    nginx \
    supervisor \
    sqlite3 \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_sqlite mbstring bcmath zip xml \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Create required directories
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

# Set correct permissions
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache \
    && find /var/www/storage -type d -exec chmod 775 {} \; \
    && find /var/www/storage -type f -exec chmod 664 {} \; \
    && chmod 666 database/database.sqlite

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Setup Nginx
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && echo "daemon off;" >> /etc/nginx/nginx.conf \
    && nginx -t

# Laravel setup
RUN php artisan key:generate --force \
    && php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan cache:clear \
    && php artisan migrate --force

# Expose port
EXPOSE 80

# Start services with Supervisor
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]