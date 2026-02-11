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
FROM php:8.3-fpm
WORKDIR /var/www
RUN apt-get update && apt-get install -y \
    libpq-dev libzip-dev nginx \
    && pecl install redis && docker-php-ext-enable redis \
    && docker-php-ext-install pdo pdo_pgsql zip opcache \
    && rm -rf /var/lib/apt/lists/*
COPY docker/nginx.conf /etc/nginx/sites-available/default
COPY --from=builder /var/www /var/www
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
  CMD php -r "exit(file_get_contents('http://localhost:80/api/health') === false ? 1 : 0);"
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
