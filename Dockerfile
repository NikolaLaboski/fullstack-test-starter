FROM php:8.3-cli

WORKDIR /app

# Copy composer.json и composer.lock 
COPY composer.json composer.lock* /app/

# Allow composer as root
ENV COMPOSER_ALLOW_SUPERUSER=1

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install PHP MySQL extension
RUN docker-php-ext-install pdo_mysql

# Install PHP dependencies 
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

# Copy (src, public, итн.)
COPY . /app

# Expose app Railway
EXPOSE 8080
CMD ["sh","-lc","php -S 0.0.0.0:${PORT:-8080} -t public public/index.php"]
