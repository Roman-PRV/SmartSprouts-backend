#!/bin/bash
set -e

# Fix permissions ONLY for runtime-writable directories (storage & bootstrap/cache).
# Doing a recursive chown on the entire /var/www is extremely slow on bind-mounts
# (Windows/WSL2) and causes the container to appear stuck, leading to restart loops.
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Run migrations automatically only in production.
# In development, migrations are run manually or via dev tooling.
if [ "${APP_ENV}" = "production" ]; then
  echo "[entrypoint] Running migrations..."
  php artisan migrate --force
fi

exec "$@"
