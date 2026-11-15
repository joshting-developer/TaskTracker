#!/bin/sh
# A deployment job only: production credentials never enter the build or test jobs.
set -eu
: "${FLY_API_TOKEN:?app-scoped deployment token required}"
: "${MIGRATION_DB_USERNAME:?migration account required}"
: "${MIGRATION_DB_PASSWORD:?migration password required}"
: "${RELEASE_ARCHIVE:?verified image archive required}"
: "${RELEASE_ARCHIVE_SHA:?verified archive hash required}"
: "${RELEASE_IMAGE_ID:?verified image ID required}"
: "${RELEASE_SOURCE_SHA:?immutable source SHA required}"
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
sh tools/check-release-archive.sh
flyctl auth docker >/dev/null
target="registry.fly.io/tasktracker-josh:$RELEASE_TAG"
if docker manifest inspect "$target" > "$folder/existing.json" 2> "$folder/manifest-error.log"; then
  # A retry may follow a successful deployment whose final HTTP check failed.
  # Reuse only the exact verified image; never overwrite a published version.
  docker pull "$target"
  if test "$(docker inspect --format '{{.Id}}' "$target")" != "$RELEASE_IMAGE_ID"; then
    echo 'Existing Fly version differs from the verified image' >&2
    exit 1
  fi
  echo 'Reusing identical published version image'
else
  grep -Eiq 'manifest unknown|no such manifest|not found' "$folder/manifest-error.log"
  docker tag tasktracker-release:candidate "$target"
  docker push "$target"
fi
# Migrate with the exact tested image in a service-free Machine, then deploy HTTP.
python3 tools/fly-migrate.py --image "$target"
# Only this image is deployed; no source build, seed or reset.
flyctl deploy --app tasktracker-josh --config fly.toml --image "$target" --strategy rolling --wait-timeout 5m
curl --fail --silent --show-error --retry 3 --retry-delay 2 --max-time 45 https://task-tracker.josh-ting.com/api/health >/dev/null
python3 - <<'PY'
import json,urllib.request
request = urllib.request.Request('https://task-tracker.josh-ting.com/api/auth/registration-policy',
    headers={'User-Agent': 'TaskTrackerRelease/1.0', 'Accept': 'application/json'})
with urllib.request.urlopen(request,timeout=15) as r:
    assert json.load(r)['data']['publicRegistration'] is True
print('PASS: deployed HTTP health and public registration policy')
PY
printf 'Deployed `%s` from verified image `%s`, source `%s`\n' "$target" "$RELEASE_IMAGE_ID" "$RELEASE_SOURCE_SHA" >> "${GITHUB_STEP_SUMMARY:-/dev/null}"
