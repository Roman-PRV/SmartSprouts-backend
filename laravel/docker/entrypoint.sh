#!/bin/bash
set -e

# Populate the shared laravel_public volume from the image's public-src backup.
# This makes nginx able to serve static assets and index.php without a host bind-mount.
# In dev the volume is overridden by a bind-mount (docker-compose.override.yml).
if [ -d /var/www/public-src ]; then
  cp -a /var/www/public-src/. /var/www/public/
fi

# Re-create the storage symlink (public/storage → ../storage/app/public).
# .dockerignore excludes public/storage, so it is absent from public-src.
php artisan storage:link --force

# Fix permissions ONLY for runtime-writable directories (storage & bootstrap/cache).
# Doing a recursive chown on the entire /var/www is extremely slow on bind-mounts
# (Windows/WSL2) and causes the container to appear stuck, leading to restart loops.
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Run migrations automatically only in production.
# In development, migrations are run manually or via dev tooling.
if [ "${APP_ENV}" = "production" ]; then
  echo "[entrypoint] Caching config, routes and views..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  echo "[entrypoint] Running migrations..."
  php artisan migrate --force
fi

exec "$@"
