#!/bin/sh
set -eu

if [ -z "${APP_KEY:-}" ] && [ -n "${APP_KEY_BASE64:-}" ]; then
    export APP_KEY="base64:${APP_KEY_BASE64}"
fi

mkdir -p \
    storage/app/private \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

php artisan package:discover --ansi
php artisan config:cache --ansi
php artisan view:cache --ansi

exec "$@"
