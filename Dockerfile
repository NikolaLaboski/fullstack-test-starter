FROM php:8.3-cli

WORKDIR /app
COPY . /app

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN if [ -f composer.json ]; then \
      composer install --no-dev --prefer-dist --no-interaction --no-progress || true; \
    fi

# Enable PDO MySQL
RUN docker-php-ext-install pdo_mysql

# Serve the carcass entry (public/index.php) and keep health OK on "/"
EXPOSE 8080
CMD ["sh","-lc","php -S 0.0.0.0:${PORT:-8080} -t public public/index.php"]
