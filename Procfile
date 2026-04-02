web: frankenphp php-server --root public/ --listen :$PORT
worker: php artisan queue:work --tries=3 --timeout=90
scheduler: while true; do php artisan schedule:run --no-interaction & sleep 60; done
