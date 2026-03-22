web: /usr/local/bin/entrypoint.sh frankenphp php-server --root public/
worker: /usr/local/bin/entrypoint.sh php artisan queue:work --tries=3 --timeout=90
scheduler: /usr/local/bin/entrypoint.sh php artisan schedule:work
