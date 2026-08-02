#!/bin/sh
set -e

# ---------------------------------------------------------------------------
# APP_URL guard — Laravel uses this for EVERY artisan command (SetRequestForConsole).
# A malformed value (missing scheme, spaces, bad host) crashes boot with:
#   "Invalid URI: Host is malformed."
# ---------------------------------------------------------------------------
case "${APP_URL:-}" in
    http://*|https://*)
        if ! echo "$APP_URL" | grep -qE '^https?://[^/[:space:]]+'; then
            echo "WARNING: APP_URL looks invalid ('$APP_URL'). Using http://localhost"
            export APP_URL=http://localhost
        fi
        ;;
    *)
        echo "WARNING: APP_URL is missing or has no scheme ('${APP_URL:-<empty>}'). Using http://localhost"
        export APP_URL=http://localhost
        ;;
esac

# ---------------------------------------------------------------------------
# APP_KEY guard — generate one if not set.
# Use PHP directly (not `artisan key:generate`) so we don't need Laravel to
# boot first. In Coolify, also set APP_KEY in env vars so it persists across
# redeploys — otherwise sessions/tokens reset every restart.
# ---------------------------------------------------------------------------
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
    export APP_KEY="base64:$(php -r 'echo base64_encode(random_bytes(32));')"
    echo "==> Generated APP_KEY for this container run."
    echo "    Set APP_KEY in Coolify env to persist it across redeploys."
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
