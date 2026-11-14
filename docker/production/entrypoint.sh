#!/bin/sh
# 啟動PHP-FPM與Nginx；收到SIGTERM時兩者都優雅結束（Nginx quit、FPM QUIT等待進行中請求）。
# 不執行migration／seed；發布流程另以`php artisan migrate --force`控管。
set -eu
# 發布與維護指令直接執行, 不額外啟動HTTP程序。
if [ "$#" -gt 0 ]; then exec "$@"; fi
: "${APP_KEY:?必須以Secret提供APP_KEY}"
sed "s/__PORT__/${PORT}/" /etc/nginx/nginx.conf > /tmp/nginx/nginx.conf
php artisan config:cache --no-interaction >/dev/null
php artisan route:cache --no-interaction >/dev/null
php-fpm --nodaemonize &
fpm=$!
nginx -c /tmp/nginx/nginx.conf -g 'daemon off;' &
web=$!
stop() {
  kill -QUIT "$web" 2>/dev/null || true
  kill -QUIT "$fpm" 2>/dev/null || true
}
trap stop TERM INT
# 任一程序結束即整個容器結束，交給平台重啟。
while kill -0 "$fpm" 2>/dev/null && kill -0 "$web" 2>/dev/null; do sleep 1 & wait $! || true; done
stop
wait "$web" 2>/dev/null || true
wait "$fpm" 2>/dev/null || true
