#!/bin/sh
set -eu
# The upstream PHP binaries include both SQLite drivers statically; omitting ext-install is insufficient.
apt-get update
apt-get install -y --no-install-recommends $PHPIZE_DEPS libxml2-dev libargon2-dev libcurl4-openssl-dev libonig-dev libreadline-dev libsodium-dev libssl-dev zlib1g-dev
docker-php-source extract
cd /usr/src/php
configure_command="$(php -i | sed -n 's/^Configure Command =>  //p')"
test -n "$configure_command"
eval "$configure_command --without-pdo-sqlite --without-sqlite3"
make -j"$(nproc)"
make install
docker-php-source delete
rm -rf /var/lib/apt/lists/*
php -r 'if (extension_loaded("pdo_sqlite") || extension_loaded("sqlite3")) { exit(1); }'
