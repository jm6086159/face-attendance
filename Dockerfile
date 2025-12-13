# Laravel + Nginx + PHP-FPM + SQLite (Single Container) - build-time safe
FROM php:8.2-fpm

# Install system packages and PHP extensions
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

# Copy application files early so composer can run
COPY . .

# Create required directories and placeholder database file
RUN mkdir -p \
    storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    database \
    bootstrap/cache \
    /var/log/nginx \
    /var/lib/nginx \
    && touch database/database.sqlite || true

# Set correct permissions for runtime (www-data)
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 storage bootstrap/cache \
    && find /var/www/storage -type d -exec chmod 775 {} \; \
    && find /var/www/storage -type f -exec chmod 664 {} \; \
    && chmod 666 database/database.sqlite || true

# Install PHP dependencies (vendor)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist || true

# Copy configuration files
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
# We'll put supervisord main config at /etc/supervisor/supervisord.conf
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf

# Symlink site and test nginx config
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
    && echo "daemon off;" >> /etc/nginx/nginx.conf \
    && nginx -t

# Copy entrypoint script
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose HTTP port
EXPOSE 80

# Use the entrypoint to prepare runtime environment and start supervisord
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
