# Simple development Dockerfile for Laravel backend
FROM php:8.2-cli

# Install system deps required by Laravel
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
  && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install composer (copy from official composer image)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copy app (mounted volume will replace during dev)
COPY . /var/www

# Install PHP dependencies (best-effort during image build)
RUN composer install --no-interaction --prefer-dist --optimize-autoloader || true

# Expose the PHP dev server port
EXPOSE 8000

# Default command for development
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
