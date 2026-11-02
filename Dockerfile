
# ==========================================
# STAGE 1: Build Vue / Vite frontend
# ==========================================
FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

# Copy project files
COPY . .

# Build frontend assets
RUN npm run build


# ==========================================
# STAGE 2: Laravel PHP + Apache production
# ==========================================
FROM php:8.4-apache AS production

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libcurl4-openssl-dev \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        bcmath \
        gd \
        zip \
        mbstring \
        exif \
        pcntl \
        opcache \
        curl \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*


# ==========================================
# APACHE CONFIGURATION
# ==========================================
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf

RUN printf '<Directory /var/www/html/public>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>\n' \
    > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel


# ==========================================
# COMPOSER
# ==========================================
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --no-scripts \
    --no-autoloader


# ==========================================
# LARAVEL APPLICATION
# ==========================================
COPY . .

RUN composer dump-autoload \
    --no-dev \
    --optimize \
    --no-scripts


# ==========================================
# FRONTEND BUILD ASSETS
# ==========================================
COPY --from=frontend /app/public/build ./public/build


# ==========================================
# STORAGE PERMISSIONS
# ==========================================
RUN mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache


# ==========================================
# RENDER PORT
# ==========================================
EXPOSE 10000


# ==========================================
# APPLICATION STARTUP
# ==========================================
# 1. Configure Apache port
# 2. Discover Laravel packages
# 3. Cache Laravel configuration
# 4. Start Apache

CMD ["bash", "-c", "set -e; PORT=${PORT:-10000}; sed -ri \"s/^Listen 80$/Listen ${PORT}/\" /etc/apache2/ports.conf; sed -ri \"s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/\" /etc/apache2/sites-available/000-default.conf; php artisan package:discover --ansi; php artisan config:cache; exec apache2-foreground"]
