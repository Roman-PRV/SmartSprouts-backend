# ─────────────────────────────────────────────────────────────────────────────
# Stage 1: base — System packages, PHP extensions, Composer, application code
# ─────────────────────────────────────────────────────────────────────────────
FROM php:8.2-fpm AS base

RUN apt-get update \
  && apt-get install -y \
  zip unzip curl git \
  libzip-dev \
  libpng-dev libjpeg-dev libfreetype6-dev \
  libonig-dev \
  default-libmysqlclient-dev \
  libfcgi-bin \
  && docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install \
  gd \
  pdo \
  pdo_mysql \
  zip \
  mbstring \
  opcache \
  && docker-php-ext-enable opcache \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./

# Install all dependencies (including dev) — pruned in the prod stage below
RUN composer install \
  --no-scripts \
  --no-interaction \
  --prefer-dist

COPY . .

# Keep a copy of public/ outside the volume mount point.
# entrypoint.sh uses this to initialise the shared laravel_public volume on first start.
RUN cp -a /var/www/public /var/www/public-src

RUN composer dump-autoload --optimize \
  && php artisan package:discover --ansi

COPY docker/entrypoint.sh /entrypoint.sh
COPY docker/www.conf     /usr/local/etc/php-fpm.d/www.conf
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]

# ─────────────────────────────────────────────────────────────────────────────
# Stage 2: dev — Adds Xdebug on top of base (used via docker-compose.override)
# ─────────────────────────────────────────────────────────────────────────────
FROM base AS dev

# Install Xdebug (dev only)
RUN pecl install xdebug \
  && docker-php-ext-enable xdebug

COPY docker/xdebug.ini      /usr/local/etc/php/conf.d/xdebug.ini
COPY docker/opcache.dev.ini /usr/local/etc/php/conf.d/opcache.ini

# ─────────────────────────────────────────────────────────────────────────────
# Stage 3: prod — Optimised image without Xdebug or dev dependencies
# ─────────────────────────────────────────────────────────────────────────────
FROM base AS prod

# Re-install without dev dependencies and run Laravel optimisations
RUN composer install \
  --no-dev \
  --no-scripts \
  --no-interaction \
  --prefer-dist \
  --optimize-autoloader \
  --classmap-authoritative \
  && composer dump-autoload \
  --optimize \
  --classmap-authoritative \
  --no-dev

# NOTE: artisan config:cache / route:cache / view:cache are intentionally NOT run here.
# .env is excluded from the build context (.dockerignore), so caching here would bake
# in empty/default values (127.0.0.1, etc.). These commands run in entrypoint.sh instead,
# where real environment variables from docker-compose are available.

COPY docker/opcache.prod.ini /usr/local/etc/php/conf.d/opcache.ini
