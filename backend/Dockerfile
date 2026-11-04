FROM php:8.5.12-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libsqlite3-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" intl pdo_sqlite pcntl bcmath zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY backend/ ./
RUN composer dump-autoload --optimize --no-interaction \
    && mkdir -p /data storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data /data storage bootstrap/cache
USER www-data
EXPOSE 3002 8080
ENTRYPOINT ["sh", "/app/docker/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=3002", "--no-reload"]
