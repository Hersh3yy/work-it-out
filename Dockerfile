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
FROM php:8.4-fpm-alpine AS production

# ─── php-extension-installer (handles PECL + Alpine edge cases cleanly) ──────
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# ─── System packages + PHP extensions ────────────────────────────────────────
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
    && install-php-extensions \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_mysql \
        redis \
        sockets \
        xml \
        zip

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
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

# Coolify and Docker Swarm use this to know when the app is ready.
# /up is Laravel's built-in health endpoint (returns 200 when bootstrapped).
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/up || exit 1

ENTRYPOINT ["/entrypoint.sh"]
