#!/bin/sh
# 固定隔離專案；不繼承主服務的Compose設定／資料卷／憑證。
set -eu
unset COMPOSE_FILE COMPOSE_PROFILES COMPOSE_PROJECT_NAME DB_PASSWORD REDIS_HOST REDIS_USERNAME REDIS_PASSWORD REDIS_PREFIX MAIL_HOST TASKTRACKER_DATA_NETWORK_EXTERNAL TASKTRACKER_MAIL_CA_VOLUME TASKTRACKER_TEST_DATABASE TASKTRACKER_TEST_USERNAME TASKTRACKER_TEST_PASSWORD TASKTRACKER_IMPORT_DATABASE TASKTRACKER_IMPORT_USERNAME TASKTRACKER_IMPORT_PASSWORD
cd "$(dirname "$0")/.."
export TASKTRACKER_TEST_PORT=13049
export E2E_DOCS_PORT=5185
export E2E_API_URL=http://127.0.0.1:13049
export E2E_ISOLATED=1
export APP_ENV=testing
export TASKTRACKER_MYSQL_TEST=1
export TASKTRACKER_STANDALONE_TEST=1
export DB_HOST=mysql
export DB_DATABASE=tasktracker_test
export DB_USERNAME=tasktracker_test
export DB_PASSWORD_FILE=/run/tasktracker-app/password
export TASKTRACKER_API_PORT=13049
export TASKTRACKER_MAIL_UI_PORT=14049
export TASKTRACKER_DATA_NETWORK=tasktracker-contract-ci-data
export APP_URL=http://127.0.0.1:5185
export ALLOWED_ORIGINS=http://127.0.0.1:5185,http://127.0.0.1:5175
export TASKTRACKER_AUTH_THROTTLE_SCALE=50
export CACHE_STORE=file
export QUEUE_CONNECTION=sync
export BROADCAST_CONNECTION=null
compose() { docker compose --env-file /dev/null -p tasktracker-contract-ci -f docker-compose.yml --profile standalone "$@"; }
cleanup() { compose down; }
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
cleanup
compose up -d --build --wait backend mysql redis mailpit
python3 - <<'HEALTH'
import time, urllib.error, urllib.request
for attempt in range(90):
    try:
        with urllib.request.urlopen('http://127.0.0.1:13049/api/health', timeout=2) as response:
            if response.status == 200:
                break
    except (urllib.error.URLError, OSError):
        pass
    time.sleep(1)
else:
    raise SystemExit('Isolated API did not become healthy')
HEALTH
"${OPENAPI_PYTHON:-.local/openapi-validation/bin/python}" tools/check_openapi.py
"${OPENAPI_PYTHON:-.local/openapi-validation/bin/python}" tools/check_openapi_test.py
# Full-suite subprocesses also need the test-only memory limit.
compose exec -T -u root backend sh -c 'echo memory_limit=512M > /usr/local/etc/php/conf.d/test-memory.ini'
compose exec -T backend php -d memory_limit=512M vendor/bin/phpunit --do-not-cache-result
python3 tools/documented_examples_check.py
E2E_ISOLATED=1 python3 tools/public_attachment_acceptance.py
python3 tools/public_client_acceptance.py
npm --prefix frontend run test:docs
# 限流會耗盡隔離IP桶，必須最後執行。
python3 tools/public_api_http_check.py
