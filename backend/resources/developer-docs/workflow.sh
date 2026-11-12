#!/bin/sh
set -eu
[ "${CONFIRM_WRITE:-}" = 1 ] || { echo '此範例會建卡／更新／留言，請明確設定CONFIRM_WRITE=1' >&2; exit 1; }
: "${BASE_URL:?請提供部署origin}" "${TASKTRACKER_TOKEN:?請私下提供PAT}" "${PROJECT_ID:?請選可寫入Project}"
work=$(mktemp -d)
chmod 700 "$work"
trap 'rm -rf "$work"' EXIT HUP INT TERM
base=${BASE_URL%/}
prefix="$base/api/public/v1/projects/$PROJECT_ID"
uuid() { python3 -c 'import uuid; print(uuid.uuid4())'; }
field() { python3 -c 'import json,sys; v=json.load(sys.stdin); path=sys.argv[1].split(".");
for k in path: v=v[int(k)] if isinstance(v,list) else v[k]
print(v)' "$1"; }
req() {
  if [ "$#" = 3 ]; then
    curl --fail-with-body --silent --show-error -X "$1" \
      -H "Authorization: Bearer $TASKTRACKER_TOKEN" -H 'Content-Type: application/json' --data "$3" "$2"
  else
    curl --fail-with-body --silent --show-error -X "$1" -H "Authorization: Bearer $TASKTRACKER_TOKEN" "$2"
  fi
}
req GET "$base/api/public/v1/me" > "$work/me.json"
req GET "$base/api/public/v1/projects?perPage=20" > "$work/projects.json"
req GET "$prefix/statuses" > "$work/states.json"
python3 -c 'import json,sys; assert any(s["id"]=="closed" for s in json.load(open(sys.argv[1]))["data"]["items"])' "$work/states.json"
create_key=$(uuid)
payload=$(python3 -c 'import json,sys;print(json.dumps({"title":"cURL HTTP example", "requestId":sys.argv[1]}))' "$create_key")
req POST "$prefix/issues" "$payload" > "$work/create.json"
req POST "$prefix/issues" "$payload" > "$work/replay.json"
python3 -c 'import json,sys; assert json.load(open(sys.argv[1]))==json.load(open(sys.argv[2]))' "$work/create.json" "$work/replay.json"
id=$(field data.id < "$work/create.json")
identifier=$(field data.identifier < "$work/create.json")
curl --fail-with-body --silent --show-error --get -H "Authorization: Bearer $TASKTRACKER_TOKEN" \
  --data-urlencode "identifier=$identifier" "$prefix/issues" > "$work/list.json"
[ "$(field data.total < "$work/list.json")" = 1 ]
req GET "$prefix/issues/$id" > "$work/detail.json"
revision=$(field data.revision < "$work/detail.json")
payload=$(python3 -c 'import json,sys; print(json.dumps({"state":"closed","expectedRevision":int(sys.argv[1]),"requestId":sys.argv[2]}))' "$revision" "$(uuid)")
req PATCH "$prefix/issues/$id" "$payload" > "$work/patch.json"
revision=$(field data.revision < "$work/patch.json")
payload=$(python3 -c 'import json,sys; print(json.dumps({"content":"Verified using cURL public API.","expectedRevision":int(sys.argv[1]),"requestId":sys.argv[2]}))' "$revision" "$(uuid)")
req POST "$prefix/issues/$id/comments" "$payload" > "$work/comment.json"
req POST "$prefix/issues/$id/comments" "$payload" > "$work/comment-replay.json"
python3 -c 'import json,sys; assert json.load(open(sys.argv[1]))==json.load(open(sys.argv[2]))' "$work/comment.json" "$work/comment-replay.json"
req GET "$prefix/issues/$id" > "$work/final.json"
req GET "$prefix/issues/$id/comments" > "$work/comments.json"
[ "$(field data.state < "$work/final.json")" = closed ]
[ "$(field data.total < "$work/comments.json")" = 1 ]
echo 'PASS: cURL建卡一次、卡號定位、讀最新revision、PATCH closed、留言一次並驗證'
