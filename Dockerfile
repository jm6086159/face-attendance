# Use PHP CLI so we can run built-in server (php -S)
FROM php:8.2-cli

# set working dir
WORKDIR /var/www/html

# Install system dependencies required for extensions and builds
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    git \
    unzip \
    curl \
    zip \
    sqlite3 \
    pkg-config \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zlib1g-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Configure GD to use freetype and jpeg (required flags for docker-php-ext-install)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install PHP extensions (PDO, sqlite, mbstring, bcmath, gd, zip)
RUN docker-php-ext-install pdo pdo_sqlite mbstring bcmath gd zip

# Install composer (copy from official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy application code
COPY . .

# Ensure database directory exists and create sqlite file so build won't fail later
RUN mkdir -p database \
    && touch database/database.sqlite \
    && chmod 664 database/database.sqlite

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Set correct permissions for Laravel storage and cache (adjust user as needed)
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database || true \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database || true

# Expose a port (Render will forward $PORT automatically)
EXPOSE 8000

# Use PHP built-in server and bind to $PORT (Render sets $PORT env)
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t public"]
