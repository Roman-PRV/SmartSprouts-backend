#!/bin/bash

chown -R www-data:www-data /var/www
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# php artisan migrate --force || true

exec php-fpm
