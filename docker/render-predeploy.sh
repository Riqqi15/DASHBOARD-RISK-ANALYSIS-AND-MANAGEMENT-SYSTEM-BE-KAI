#!/bin/sh
set -eu

attempt=1
max_attempts=30

while ! php artisan migrate --force; do
    if [ "$attempt" -ge "$max_attempts" ]; then
        echo "Database migration failed after ${max_attempts} attempts." >&2
        exit 1
    fi

    attempt=$((attempt + 1))
    echo "Database is not ready; retrying migration (${attempt}/${max_attempts})..." >&2
    sleep 5
done
