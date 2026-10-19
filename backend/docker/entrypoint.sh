#!/bin/sh
set -eu
if [ "${TASKTRACKER_BOOTSTRAP:-0}" = "1" ]; then
  php /app/docker/initialize.php
  php artisan migrate --force --no-interaction
fi
exec "$@"
