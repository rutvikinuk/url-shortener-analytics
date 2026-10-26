# syntax=docker/dockerfile:1
#
# Three stages: build the React dashboard, install PHP dependencies, then copy
# both into a small runtime image. Only the last stage ends up in the image.

# ---- 1. React dashboard -> static files -------------------------------------
FROM node:22-alpine AS frontend
WORKDIR /frontend
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
# The dashboard is served from /dashboard/ (a reserved slug, so no short link can clash)
RUN npx vite build --base=/dashboard/

# ---- 2. PHP dependencies (no dev packages) ----------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# The runtime image has the real PHP extensions, so skip platform checks here.
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ---- 3. Runtime --------------------------------------------------------------
FROM php:8.3-cli-alpine AS runtime
WORKDIR /var/www/html

COPY --from=vendor /app/ ./
COPY --from=frontend /frontend/dist/ ./public/dashboard/
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/data/database.sqlite \
    SESSION_DRIVER=file \
    CACHE_STORE=file \
    QUEUE_CONNECTION=sync \
    PHP_CLI_SERVER_WORKERS=4

# Run as the unprivileged www-data user. /data holds the SQLite file (mount a volume there).
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p /data storage/framework/cache/data storage/framework/sessions \
        storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data /data storage bootstrap/cache

USER www-data
VOLUME ["/data"]
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD wget -qO- http://127.0.0.1:8000/up >/dev/null || exit 1

ENTRYPOINT ["entrypoint.sh"]
