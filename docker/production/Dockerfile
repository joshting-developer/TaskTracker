# 正式HTTP runtime：前端Vite靜態檔＋Laravel API同一映像（Nginx＋PHP-FPM），非artisan serve。
# 建置：docker build -f docker/production/Dockerfile -t tasktracker:prod .
# 映像不含秘密、.env、資料庫、測試與開發工具；migration由發布流程另外執行，不在啟動時自動跑。

FROM node:24-bookworm-slim AS frontend
WORKDIR /build
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --include=dev --no-audit --no-fund
COPY frontend/index.html frontend/vite.config.js ./
COPY frontend/src ./src
RUN npm run build

FROM php:8.5.12-fpm-bookworm AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends nginx libicu-dev libzip-dev unzip curl \
    && docker-php-ext-install -j"$(nproc)" intl pdo_mysql pcntl bcmath zip \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled /etc/nginx/conf.d/* \
    && rm -f /usr/local/etc/php-fpm.d/www.conf.default /usr/local/etc/php-fpm.d/zz-docker.conf
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader
COPY backend/artisan ./
COPY backend/app ./app
COPY backend/bootstrap ./bootstrap
COPY backend/config ./config
COPY backend/database ./database
COPY backend/public ./public
COPY backend/routes ./routes
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction \
    && rm /usr/local/bin/composer \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=frontend /build/dist /srv/frontend
COPY docker/production/nginx.conf /etc/nginx/nginx.conf
COPY docker/production/php-fpm.conf /usr/local/etc/php-fpm.d/zz-tasktracker.conf
COPY docker/production/php.ini /usr/local/etc/php/conf.d/zz-tasktracker.ini
COPY docker/production/entrypoint.sh /usr/local/bin/tasktracker-entrypoint
RUN chmod 0755 /usr/local/bin/tasktracker-entrypoint && mkdir -p /tmp/nginx && chown -R www-data:www-data /tmp/nginx
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr PORT=8080 PHP_FPM_MAX_CHILDREN=6
USER www-data
EXPOSE 8080
STOPSIGNAL SIGTERM
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 CMD curl -fsS "http://127.0.0.1:${PORT}/api/health" >/dev/null || exit 1
ENTRYPOINT ["/usr/local/bin/tasktracker-entrypoint"]
