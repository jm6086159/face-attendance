# Dockerfile (single container: nginx + php-fpm + sqlite)
FROM php:8.2-fpm

# System deps
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y \
    nginx supervisor sqlite3 libsqlite3-dev \
    libpng-dev libjpeg-dev libfreetype6-dev libonig-dev libxml2-dev libzip-dev \
    zip unzip git curl \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd pdo pdo_sqlite mbstring bcmath zip xml \
  && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# copy app
COPY . .

# create folders and sqlite
RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} database bootstrap/cache /var/log/nginx /var/lib/nginx \
 && touch database/database.sqlite \
 && chown -R www-data:www-data /var/www \
 && chmod -R 775 storage bootstrap/cache database \
 && chmod 666 database/database.sqlite

# install php deps
RUN composer install --no-dev --optimize-autoloader --no-interaction

# copy configs (assumes these paths)
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# enable nginx site
RUN ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default \
 && echo "daemon off;" >> /etc/nginx/nginx.conf \
 && nginx -t || true

# ensure env & cache (do not run artisan migrate if DB should be seeded externally)
RUN php artisan key:generate --force || true \
 && php artisan config:clear || true \
 && php artisan cache:clear || true

EXPOSE 80

CMD ["/usr/bin/supervisord","-n","-c","/etc/supervisor/conf.d/supervisord.conf"]
