#!/bin/sh
set -e

# ---------------------------------------------------------------------------
# APP_KEY guard — generate one if not set.
# Without a key, all encrypted values (sessions, tokens) are broken silently.
# ---------------------------------------------------------------------------
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    echo "==> APP_KEY is not set — generating a fresh key..."
    php artisan key:generate --force --no-interaction
fi

# ---------------------------------------------------------------------------
# Package discovery — must run first so bootstrap cache only knows
# about production packages (no dev deps).
# ---------------------------------------------------------------------------
echo "==> Discovering packages (production vendor)..."
php artisan package:discover --ansi

# ---------------------------------------------------------------------------
# Clear all stale build-time caches, then re-cache with the runtime env.
# The runtime env contains real DB/Redis/AI credentials not present at build.
# ---------------------------------------------------------------------------
echo "==> Clearing stale build-time caches..."
php artisan config:clear  --no-interaction 2>/dev/null || true
php artisan route:clear   --no-interaction 2>/dev/null || true
php artisan view:clear    --no-interaction 2>/dev/null || true
php artisan event:clear   --no-interaction 2>/dev/null || true

echo "==> Re-caching with runtime environment..."
php artisan config:cache  --no-interaction
php artisan route:cache   --no-interaction
php artisan view:cache    --no-interaction
php artisan event:cache   --no-interaction

# ---------------------------------------------------------------------------
# Storage link — needed for file serving via Nginx.
# Force flag is safe; it only replaces if the link already exists.
# ---------------------------------------------------------------------------
echo "==> Linking storage..."
php artisan storage:link --force --no-interaction 2>/dev/null || true

# ---------------------------------------------------------------------------
# Wait for the database to be ready, then run migrations.
# Loops for up to 90 seconds (30 × 3s) before giving up.
# ---------------------------------------------------------------------------
echo "==> Waiting for database and running migrations..."
RETRIES=30
until php artisan migrate --force --no-interaction 2>&1; do
    RETRIES=$((RETRIES - 1))
    if [ "$RETRIES" -le 0 ]; then
        echo "ERROR: Database never became available. Exiting."
        exit 1
    fi
    echo "  Database not ready yet — retrying in 3s ($RETRIES attempts left)..."
    sleep 3
done

echo "==> Migrations complete. Starting Supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
