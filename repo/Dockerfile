# ---- Stage 1: Composer dependencies ----
FROM composer:2.7 AS vendor

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-reqs

# ---- Stage 2: Production image ----
FROM php:8.3-fpm AS production

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libldap2-dev \
    zip \
    unzip \
    default-mysql-client \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        zip \
        ldap \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set PHP configuration
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/zz-meridian.conf

# Set working directory
WORKDIR /var/www/html

# Copy vendor from composer stage
COPY --from=vendor /app/vendor ./vendor

# Copy application source
COPY . .

# Copy and set entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Create storage structure and set permissions
RUN mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache \
    /backups \
    && chown -R www-data:www-data /var/www/html \
    && chown www-data:www-data /backups \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
