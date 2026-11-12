#!/bin/sh
# Own only this isolated topology; never inherit the live DB, ports or volumes.
set -eu
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
export TASKTRACKER_TEST_PORT=13079
export E2E_API_URL=http://127.0.0.1:13079
compose() { docker compose -p tasktracker-codex-visual -f docker-compose.mysql-test.yml "$@"; }
cleanup() {
  status=$?
  trap - EXIT
  compose down --volumes || status=1
  rmdir "$lock" || status=1
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
compose down --volumes
if ! docker image inspect tasktracker-backend >/dev/null 2>&1; then docker build -t tasktracker-backend -f backend/Dockerfile .; fi
compose up -d --wait backend
# Test-only fixture safety is part of the same isolated topology, not a product HTTP endpoint.
compose exec -T -e TASKTRACKER_MYSQL_TEST=0 backend php artisan test --filter=VisualFixtureGuardTest --do-not-cache-result
python3 tools/visual-baseline.py "$mode"
