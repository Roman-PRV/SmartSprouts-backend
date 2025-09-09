FROM php:8.2-fpm

RUN apt-get update \
  && apt-get install -y \
  zip unzip curl git \
  libzip-dev \
  libpng-dev libjpeg-dev libfreetype6-dev \
  libonig-dev \
  default-libmysqlclient-dev \
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

COPY docker/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY composer.json composer.lock ./

RUN composer install \
  --no-dev \
  --no-scripts \
  --optimize-autoloader \
  --classmap-authoritative

COPY . .

RUN composer dump-autoload --optimize \
  && php artisan package:discover --ansi \
  && php artisan config:cache \
  && php artisan route:cache \
  && php artisan view:cache

COPY /docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
