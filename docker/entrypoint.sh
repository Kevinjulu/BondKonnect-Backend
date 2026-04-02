#!/bin/bash
set -e

# ============================================
# CRITICAL: Export SERVER_NAME immediately
# before anything else runs
# ============================================
LISTEN_PORT="${PORT:-8080}"
export SERVER_NAME=":${LISTEN_PORT}"
echo "=== SERVER_NAME set to ${SERVER_NAME} ==="

# Ensure storage and cache directories exist and are writable
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p bootstrap/cache
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Ensure APP_KEY is set
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generating one..."
    GEN_KEY=$(php artisan key:generate --show --no-interaction)
    export APP_KEY="$GEN_KEY"
fi

# Auto-configure APP_URL from Railway domain if not set
if [ -z "$APP_URL" ] && [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
    echo "Auto-configuring APP_URL to https://$RAILWAY_PUBLIC_DOMAIN"
    export APP_URL="https://$RAILWAY_PUBLIC_DOMAIN"
fi

# ============================================
# Database connection with retry logic
# ============================================
echo "Waiting for database..."
MAX_RETRIES=30
RETRY_COUNT=0

until php artisan db:monitor --databases=pgsql 2>/dev/null || [ $RETRY_COUNT -eq $MAX_RETRIES ]; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    echo "Database not ready, retrying ($RETRY_COUNT/$MAX_RETRIES)..."
    sleep 2
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo "WARNING: Could not confirm database connection. Proceeding anyway..."
fi

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Run seeders only if explicitly requested
if [ "$RUN_SEEDER" = "true" ]; then
    echo "Running seeders..."
    php artisan db:seed --force
fi

# Link storage
php artisan storage:link --force 2>/dev/null || true

# Optimize Laravel for production
echo "Optimizing Laravel for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ============================================
# Start the correct process based on role
# ============================================
CONTAINER_ROLE="${CONTAINER_ROLE:-app}"

if [ "$CONTAINER_ROLE" = "worker" ]; then
    echo "Starting Queue Worker..."
    exec php artisan queue:work \
        --sleep=3 \
        --tries=3 \
        --timeout=90 \
        --max-time=3600 \
        --verbose

elif [ "$CONTAINER_ROLE" = "scheduler" ]; then
    echo "Starting Scheduler..."
    while true; do
        php artisan schedule:run --verbose --no-interaction &
        sleep 60
    done

else
    echo "Starting FrankenPHP on :${LISTEN_PORT}..."
    if [ "$#" -gt 0 ]; then
        exec "$@"
    else
        exec frankenphp php-server --root /app/public/
    fi
fi
