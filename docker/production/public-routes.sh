#!/bin/sh
# TT-377：依建置目標輸出公開頁的 Nginx 路由, 由 nginx.conf include。
# combined：介紹頁跟工作區同站, 舊網址轉到本站首頁。
# app：介紹頁改由 site origin（Cloudflare Pages）提供, 舊公開網址一律 301 過去；
#      /login、/app/、/admin/、/api/ 不在這裡, 仍由本站服務。
# 根路徑在工作區自己的網域（APP_URL）用 302 進 /app/, 舊網域則 301 到介紹站。瀏覽器轉址會保留 # 後的深層連結。
set -eu
target=${1:?target required}
site=${2:-}
features='(tasks|collaboration|time|integrations)'
case "$target" in
  combined)
    cat <<CONF
location = /index.html { add_header Cache-Control "no-cache"; }
location = /about { return 301 /; }
location = /about/ { return 301 /; }
location = /en { return 301 /en/; }
location ~ ^/features/$features/?(index\.html)?\$ { return 301 /#\$1; }
location ~ ^/features(/.*)?\$ { return 301 /; }
CONF
    ;;
  app)
    printf '%s' "$site" | grep -Eq '^https?://[A-Za-z0-9.-]+(:[0-9]+)?$' || { echo 'app target requires a site origin' >&2; exit 1; }
    cat <<CONF
location = / { if (\$host = "__APP_HOST__") { return 302 /app/\$is_args\$args; } return 301 $site/\$is_args\$args; }
location = /index.html { return 301 $site/; }
location = /about { return 301 $site/; }
location = /about/ { return 301 $site/; }
location ~ ^/en(/.*)?\$ { return 301 $site/en/; }
location ~ ^/features/$features/?(index\.html)?\$ { return 301 $site/#\$1; }
location ~ ^/features(/.*)?\$ { return 301 $site/; }
location = /sitemap.xml { return 301 $site/sitemap.xml; }
CONF
    ;;
  *) echo 'target must be combined or app' >&2; exit 1 ;;
esac
