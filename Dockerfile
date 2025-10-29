FROM php:8.3-cli

WORKDIR /app

# Allow composer as root
ENV COMPOSER_ALLOW_SUPERUSER=1

# System deps needed for composer (git, unzip) + php extensions (pdo_mysql, zip)
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    zlib1g-dev \
    libzip-dev \
 && docker-php-ext-install pdo_mysql zip \
 && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy only composer files first for better cache
COPY composer.json composer.lock* /app/

# Install PHP deps (generates /app/vendor)
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress

# Now copy the rest of the app (src, public, etc.)
COPY . /app

# Serve public/ with router script; Railway injects $PORT
EXPOSE 8080
CMD ["sh","-lc","php -S 0.0.0.0:${PORT:-8080} -t public public/index.php"]
