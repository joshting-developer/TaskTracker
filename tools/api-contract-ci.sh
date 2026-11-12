#!/bin/sh
# 固定隔離專案；不繼承主服務的Compose設定／資料卷／憑證。
set -eu
cd "$(dirname "$0")/.."
export TASKTRACKER_TEST_PORT=13049
export E2E_API_URL=http://127.0.0.1:13049
compose() { docker compose -p tasktracker-contract-ci -f docker-compose.mysql-test.yml "$@"; }
cleanup() { compose down --volumes --remove-orphans; }
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
# 只重設本腳本擁有的隔離測試環境，包含前次中斷的tmpfs資料。
cleanup
docker build -t tasktracker-backend -f backend/Dockerfile .
compose up -d
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
compose exec -T -e TASKTRACKER_MYSQL_TEST=0 backend php artisan test --filter='TokenSpecificationTest|PublicApiContractTest|DeveloperDocumentationTest|PersonalTokensTest|PublicQueriesTest|PublicWritesTest' --do-not-cache-result
compose exec -T backend php artisan test --filter='MySqlPublicQueriesTest|MySqlPublicWritesTest|MySqlTokenRevocationRaceTest' --do-not-cache-result
python3 tools/documented_examples_check.py
python3 tools/public_client_acceptance.py
npm --prefix frontend run test:docs
# 限流會耗盡隔離IP桶，必須最後執行。
python3 tools/public_api_http_check.py
