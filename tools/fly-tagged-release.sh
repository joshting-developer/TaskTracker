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
: "${FLY_CONFIG_PATH:=fly.toml}"
export FLY_CONFIG_PATH
umask 077
folder=$(mktemp -d)
# Fly/Docker may echo hosts or App names; keep raw output in this invocation's private temp dir.
exec 3>&1
phase=private-config
cleanup() {
  status=$?
  trap - EXIT
  if [ "$status" -ne 0 ]; then printf 'Deployment failed at %s; private command output withheld\n' "$phase" >&3; fi
  rm -rf "$folder"
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
exec >"$folder/release.log" 2>&1
app=$(python3 tools/fly_config.py --field app)
origin=$(python3 tools/fly_config.py --field origin)
phase=preflight-health
curl --fail --silent --show-error --retry 2 --max-time 45 "$origin/api/health" >/dev/null
phase=schema-guard
# Read only migration names, never connection settings, keys, rows or other business data.
flyctl ssh console --app "$app" -C 'php -r '\''require "vendor/autoload.php";$app=require "bootstrap/app.php";$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();echo json_encode(Illuminate\Support\Facades\DB::table("migrations")->orderBy("migration")->pluck("migration"));'\''' > "$folder/applied.json"
python3 tools/check-release-migrations.py --applied-file "$folder/applied.json"
phase=config-and-image-validation
flyctl config validate --strict --config "$FLY_CONFIG_PATH"
sh tools/check-release-archive.sh
flyctl auth docker >/dev/null
phase=registry-publication
target="registry.fly.io/$app:$RELEASE_TAG"
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
phase=migration
# Migrate with the exact tested image in a service-free Machine, then deploy HTTP.
python3 tools/fly-migrate.py --config "$FLY_CONFIG_PATH" --image "$target"
phase=http-deployment
# Only this image is deployed; no source build, seed or reset.
flyctl deploy --app "$app" --config "$FLY_CONFIG_PATH" --image "$target" --strategy rolling --wait-timeout 5m
phase=post-deployment-checks
curl --fail --silent --show-error --retry 3 --retry-delay 2 --max-time 45 "$origin/api/health" >/dev/null
RELEASE_SITE_ORIGIN="$origin" python3 - <<'PY'
import json,os,urllib.request
request = urllib.request.Request(os.environ['RELEASE_SITE_ORIGIN'] + '/api/auth/registration-policy',
    headers={'User-Agent': 'TaskTrackerRelease/1.0', 'Accept': 'application/json'})
with urllib.request.urlopen(request,timeout=15) as r:
    assert json.load(r)['data']['publicRegistration'] is True
print('PASS: deployed HTTP health and public registration policy')
PY
printf 'Deployed verified version `%s`, source `%s`\n' "$RELEASE_TAG" "$RELEASE_SOURCE_SHA" >> "${GITHUB_STEP_SUMMARY:-/dev/null}"

printf 'PASS: verified release completed\n' >&3
