#!/bin/sh
# Own only this isolated topology; never inherit the live DB, ports or volumes.
set -eu
unset COMPOSE_FILE COMPOSE_PROFILES COMPOSE_PROJECT_NAME DB_PASSWORD REDIS_HOST REDIS_USERNAME REDIS_PASSWORD REDIS_PREFIX MAIL_HOST TASKTRACKER_DATA_NETWORK_EXTERNAL TASKTRACKER_MAIL_CA_VOLUME TASKTRACKER_TEST_DATABASE TASKTRACKER_TEST_USERNAME TASKTRACKER_TEST_PASSWORD TASKTRACKER_IMPORT_DATABASE TASKTRACKER_IMPORT_USERNAME TASKTRACKER_IMPORT_PASSWORD
cd "$(dirname "$0")/.."
mode=${1:-compare}
case "$mode" in candidate|compare|prove) ;; *) echo 'Usage: sh tools/visual-check.sh candidate|compare|prove' >&2; exit 2 ;; esac
if [ "$mode" = compare ]; then python3 tools/visual-baseline.py preflight; fi
umask 077
mkdir -p .local
lock=.local/visual-run.lock
if ! mkdir "$lock" 2>/dev/null; then
  echo 'Visual runner is busy or has a stale lock; do not reset another invocation.' >&2
  exit 2
fi
export E2E_COMPOSE_PROJECT=tasktracker-visual
export TASKTRACKER_VISUAL_ORIGIN=http://127.0.0.1:5195
export MAIL_DELIVERY_MODE=queue
export MAIL_MAILER=log
export TASKTRACKER_BACKEND_IMAGE=tasktracker-visual-backend
export TASKTRACKER_API_PORT=13079
export TASKTRACKER_MAIL_UI_PORT=14079
export TASKTRACKER_DATA_NETWORK=tasktracker-visual-data
export APP_ENV=testing
export DB_HOST=mysql
export DB_DATABASE=tasktracker_test
export DB_USERNAME=tasktracker_test
export DB_PASSWORD_FILE=/run/tasktracker-app/password
export TASKTRACKER_MYSQL_TEST=1
export TASKTRACKER_STANDALONE_TEST=1
export APP_URL=http://127.0.0.1:5195
export ALLOWED_ORIGINS=http://127.0.0.1:5195
export TASKTRACKER_AUTH_THROTTLE_SCALE=50
export CACHE_STORE=file
export QUEUE_CONNECTION=sync
export BROADCAST_CONNECTION=null
export E2E_API_URL=http://127.0.0.1:13079
export E2E_ISOLATED=1
compose() { docker compose --env-file /dev/null -p "$E2E_COMPOSE_PROJECT" -f docker-compose.yml --profile standalone "$@"; }
cleanup() {
  status=$?
  trap - EXIT
  compose down || status=1
  rmdir "$lock" || status=1
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
compose down
compose up -d --build --wait backend mysql redis mailpit
python3 -c "import importlib.util; s=importlib.util.spec_from_file_location('visual','tools/visual-baseline.py'); m=importlib.util.module_from_spec(s); s.loader.exec_module(m); m.reset_calibration_fixtures()"
# Test-only fixture safety is part of the same isolated topology, not a product HTTP endpoint.
compose exec -T backend php artisan test --filter=VisualFixtureGuardTest --do-not-cache-result
python3 tools/visual-baseline.py "$mode"
