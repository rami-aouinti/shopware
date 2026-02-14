#!/usr/bin/env bash
set -euo pipefail

if [ "$(id -u)" = "0" ]; then
    for path in \
        /var/www/html/public/bundles \
        /var/www/html/public/theme \
        /var/www/html/var/cache \
        /var/www/html/var/log; do
        if [ -e "$path" ]; then
            chown -R www-data:www-data "$path"
        fi
    done
fi

exec docker-php-entrypoint "$@"
