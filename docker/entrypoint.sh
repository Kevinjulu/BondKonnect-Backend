#!/bin/bash
set -e

echo "=== DEBUG: PORT=$PORT, SERVER_NAME=$SERVER_NAME ==="

# Wait for database if needed (optional, Railway handles this well)
# sleep 5

# Ensure storage and cache directories exist and are writable
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p bootstrap/cache
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Ensure APP_KEY is set
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set. Generating one..."
    # Generate and capture key without modifying any file
    GEN_KEY=$(php artisan key:generate --show --no-interaction)
    export APP_KEY="$GEN_KEY"
fi

# Auto-configure APP_URL from Railway domain if not set
if [ -z "$APP_URL" ] && [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
    echo "Auto-configuring APP_URL to https://$RAILWAY_PUBLIC_DOMAIN"
    export APP_URL="https://$RAILWAY_PUBLIC_DOMAIN"
fi

# Robust DB connection check and migration
echo "Checking database connection to ${DB_HOST:-$PGHOST}..."
MAX_TRIES=5
TRIES=0
until php artisan db:show > /dev/null 2>&1 || [ $TRIES -eq $MAX_TRIES ]; do
    echo "Waiting for database connection... ($((TRIES+1))/$MAX_TRIES)"
    sleep 2
    TRIES=$((TRIES+1))
done

if [ $TRIES -eq $MAX_TRIES ]; then
    echo "WARNING: Could not connect to database after $MAX_TRIES attempts. Proceeding anyway..."
else
    echo "Database connection established. Running migrations..."
    php artisan migrate --force
fi

# Run seeders only if explicitly requested via RUN_SEEDER env var
if [ "$RUN_SEEDER" = "true" ]; then
    echo "Running seeders..."
    php artisan db:seed --force
fi

# Link storage
php artisan storage:link --force

# Optimize Laravel for production
echo "Optimizing Laravel for production..."
# In some environments, caching during entrypoint might cause issues with dynamically injected vars.
# We only cache if explicitly requested or in production.
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

# Resolve PORT - Railway injects this dynamically
LISTEN_PORT="${PORT:-8080}"
echo "Starting FrankenPHP on port $LISTEN_PORT..."

# Set SERVER_NAME to ensure FrankenPHP listens on the correct port
# Use http:// to avoid automatic HTTPS redirection/certificates issues in Railway
export SERVER_NAME="http://:$LISTEN_PORT"

# Handle arguments if passed (for Procfile compatibility)
if [ $# -gt 0 ]; then
    echo "Executing command from arguments: $@"
    exec "$@"
else
    # Start FrankenPHP by default
    echo "Starting default FrankenPHP server..."
    exec frankenphp php-server --root /app/public/
fi
