#!/bin/bash
set -e

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
    # We use a temporary env file to generate the key without a .env existing
    touch .env
    php artisan key:generate --force
    export APP_KEY=$(grep APP_KEY .env | cut -d= -f2)
    rm .env
fi

# Auto-configure APP_URL from Railway domain if not set
if [ -z "$APP_URL" ] && [ -n "$RAILWAY_PUBLIC_DOMAIN" ]; then
    echo "Auto-configuring APP_URL to https://$RAILWAY_PUBLIC_DOMAIN"
    export APP_URL="https://$RAILWAY_PUBLIC_DOMAIN"
fi

# Run migrations unconditionally to ensure tables exist in Railway
echo "Checking database connection and running migrations..."
php artisan migrate --force

# Run seeders only if explicitly requested via RUN_SEEDER env var
if [ "$RUN_SEEDER" = "true" ]; then
    echo "Running seeders..."
    php artisan db:seed --force
fi

# Link storage
php artisan storage:link --force

# Optimize Laravel for production
echo "Optimizing Laravel for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Execute the main command (e.g., FrankenPHP server)
exec "$@"
