# Stage 1: Install dependencies
FROM php:8.3-cli AS builder
WORKDIR /var/www
RUN apt-get update && apt-get install -y \
    git unzip libpq-dev libzip-dev \
    && pecl install redis && docker-php-ext-enable redis \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize

# Stage 2: Production image
FROM php:8.3-cli
WORKDIR /var/www
RUN apt-get update && apt-get install -y \
    libpq-dev libzip-dev \
    && pecl install redis && docker-php-ext-enable redis \
    && docker-php-ext-install pdo pdo_pgsql zip opcache \
    && rm -rf /var/lib/apt/lists/*
RUN groupadd -r soleil && useradd -r -g soleil -d /var/www soleil
COPY --from=builder --chown=soleil:soleil /var/www /var/www
USER soleil
EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD php -r "exit(file_get_contents('http://localhost:8000/api/health') === false ? 1 : 0);"
# ⚠️ WARNING: php artisan serve is DEVELOPMENT ONLY.
# For production, use PHP-FPM + Nginx or Laravel Octane (FrankenPHP/Swoole).
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
