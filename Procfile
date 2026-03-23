web: /usr/local/bin/entrypoint.sh frankenphp php-server --root public/ --listen 0.0.0.0:$PORT
worker: /usr/local/bin/entrypoint.sh php artisan horizon
scheduler: /usr/local/bin/entrypoint.sh php artisan schedule:run
