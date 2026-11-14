#!/bin/sh
set -eu
: "${RELEASE_ARCHIVE:?required}"
: "${RELEASE_ARCHIVE_SHA:?required}"
: "${RELEASE_IMAGE_ID:?required}"
: "${RELEASE_SOURCE_SHA:?required}"
: "${RELEASE_TAG:?required}"
printf '%s  %s\n' "$RELEASE_ARCHIVE_SHA" "$RELEASE_ARCHIVE" | sha256sum -c -
docker load -i "$RELEASE_ARCHIVE"
test "$(docker inspect --format '{{.Id}}' tasktracker-release:candidate)" = "$RELEASE_IMAGE_ID"
test "$(docker inspect --format '{{index .Config.Labels "org.opencontainers.image.revision"}}' tasktracker-release:candidate)" = "$RELEASE_SOURCE_SHA"
test "$(docker inspect --format '{{index .Config.Labels "org.opencontainers.image.version"}}' tasktracker-release:candidate)" = "$RELEASE_TAG"
