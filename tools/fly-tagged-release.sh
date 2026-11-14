#!/bin/sh
# A deployment job only: production credentials never enter the build or test jobs.
set -eu
: "${FLY_API_TOKEN:?app-scoped deployment token required}"
: "${RELEASE_IMAGE:?verified image required}"
: "${RELEASE_DIGEST:?verified digest required}"
: "${RELEASE_TAG:?version required}"
umask 077
folder=$(mktemp -d)
cleanup() { rm -rf "$folder"; }
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
curl --fail --silent --show-error --retry 2 --max-time 45 https://task-tracker.josh-ting.com/api/health >/dev/null
# Read only migration names, never connection settings, keys, rows or other business data.
flyctl ssh console --app tasktracker-josh -C 'php -r '\''require "vendor/autoload.php";$app=require "bootstrap/app.php";$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();echo json_encode(Illuminate\Support\Facades\DB::table("migrations")->orderBy("migration")->pluck("migration"));'\''' > "$folder/applied.json"
python3 tools/check-release-migrations.py --applied-file "$folder/applied.json"
flyctl config validate --strict --config fly.toml
docker pull "$RELEASE_IMAGE@$RELEASE_DIGEST"
flyctl auth docker >/dev/null
target="registry.fly.io/tasktracker-josh:$RELEASE_TAG"
docker tag "$RELEASE_IMAGE@$RELEASE_DIGEST" "$target"
docker push "$target"
# Only this image is deployed; no source build, seed, reset or skipped release command.
flyctl deploy --app tasktracker-josh --config fly.toml --image "$target" --strategy rolling --wait-timeout 5m
curl --fail --silent --show-error --retry 3 --retry-delay 2 --max-time 45 https://task-tracker.josh-ting.com/api/health >/dev/null
python3 - <<'PY'
import json,urllib.request
with urllib.request.urlopen('https://task-tracker.josh-ting.com/api/auth/registration-policy',timeout=15) as r:
    assert json.load(r)['data']['publicRegistration'] is True
print('PASS: deployed HTTP health and public registration policy')
PY
printf 'Deployed `%s` from verified `%s@%s`\n' "$target" "$RELEASE_IMAGE" "$RELEASE_DIGEST" >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
