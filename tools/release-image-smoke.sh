#!/bin/sh
# Inspect and exercise only this synthetic runtime container, without production data or secrets.
set -eu
image=${1:?image required}
name="tasktracker-release-smoke-${GITHUB_RUN_ID:-local}-$$"
cleanup() { docker rm -f "$name" >/dev/null 2>&1 || true; }
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
key=$(python3 -c 'import base64,secrets; print("base64:"+base64.b64encode(secrets.token_bytes(32)).decode())')
docker run -d --name "$name" -p 127.0.0.1::8080 \
  -e APP_KEY="$key" -e APP_ENV=testing -e SESSION_DRIVER=array \
  -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e BROADCAST_CONNECTION=null \
  -e TASKTRACKER_PUBLIC_REGISTRATION=true "$image" >/dev/null
port=$(docker port "$name" 8080/tcp | sed 's/.*://')
python3 - "$port" <<'PY'
import json,sys,time,urllib.request,urllib.error
base='http://127.0.0.1:'+sys.argv[1]
for attempt in range(45):
    try:
        with urllib.request.urlopen(base+'/api/health', timeout=2) as r:
            if r.status==200: break
    except (urllib.error.URLError,OSError): pass
    time.sleep(1)
else: raise SystemExit('Synthetic runtime did not become healthy')
for path in ['/', '/login', '/app/', '/api/health', '/api/public/openapi.json']:
    with urllib.request.urlopen(base+path,timeout=5) as r:
        assert r.status==200,path
        if path=='/api/public/openapi.json': assert json.load(r)['openapi'].startswith('3.1')
with urllib.request.urlopen(base+'/api/auth/registration-policy',timeout=5) as r:
    assert json.load(r)['data']['publicRegistration'] is True
print('PASS: synthetic production runtime routes and public contract')
PY
docker exec "$name" sh -c 'test ! -e /app/.env && test ! -d /app/tests && test ! -d /app/tools && test ! -d /app/node_modules && test ! -d /app/.git'
docker exec "$name" php -r 'if ((int) ini_get("mysqlnd.net_read_timeout") !== 3) exit(1); echo "PASS: bounded mysqlnd reads\n";'
