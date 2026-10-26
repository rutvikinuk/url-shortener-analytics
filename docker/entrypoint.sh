#!/bin/sh
# Container start-up: make sure there is an APP_KEY and a database, then serve.
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
    APP_KEY="$(php artisan key:generate --show)"
    export APP_KEY
    echo "WARNING: APP_KEY not set, using a temporary key. Set APP_KEY to keep it stable." >&2
fi

touch "$DB_DATABASE"
php artisan migrate --force

# php -S as PID 1's child via exec so SIGTERM from `docker stop` reaches it.
# The built-in server is fine for a demo/small deploy; put nginx + php-fpm or
# FrankenPHP in front for real traffic.
cd public
exec php -S 0.0.0.0:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
