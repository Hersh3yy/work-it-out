#!/bin/sh
set -e

case "${APP_URL:-}" in
    http://*|https://*)
        ;;
    *)
        export APP_URL=http://localhost:8088
        ;;
esac

echo "==> Installing Composer dependencies (dev)..."
composer install --no-interaction --prefer-dist

echo "==> Preparing storage directories..."
mkdir -p storage/framework/{cache,sessions,views} bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache

php artisan storage:link --force --no-interaction 2>/dev/null || true

echo "==> Waiting for database and running migrations..."
RETRIES=30
until php artisan migrate --no-interaction 2>&1; do
    RETRIES=$((RETRIES - 1))
    if [ "$RETRIES" -le 0 ]; then
        echo "ERROR: Database never became available. Exiting."
        exit 1
    fi
    echo "  Database not ready yet — retrying in 3s ($RETRIES attempts left)..."
    sleep 3
done

echo "==> Dev stack ready. Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
