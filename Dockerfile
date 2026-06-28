# ============================================================================
# Stage 1: Composer dependencies
# ============================================================================
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ============================================================================
# Stage 2: Final production image
# ============================================================================
FROM php:8.5-fpm-alpine AS production

# ─── System packages ─────────────────────────────────────────────────────────
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    oniguruma-dev \
    libxml2-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        redis \
        sockets \
        xml \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# ─── PHP configuration ───────────────────────────────────────────────────────
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

# ─── Nginx configuration ─────────────────────────────────────────────────────
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf

# ─── Supervisor configuration ────────────────────────────────────────────────
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# ─── Application ─────────────────────────────────────────────────────────────
WORKDIR /var/www/html

COPY --from=vendor /app/vendor ./vendor
COPY . .

RUN mkdir -p storage/framework/{cache,sessions,views} \
    && mkdir -p bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
