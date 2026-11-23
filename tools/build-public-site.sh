#!/bin/sh
# TT-376：Cloudflare Pages 介紹站的建置指令（Git 整合, 建置根目錄為 repository 根目錄, 輸出 frontend/dist-public）。
# 網域只讀 deploy/origins.json；site 與 app 相同代表還沒拆站, 直接失敗, 不產生指向錯誤網域的頁面。
# 任何一步失敗都以非 0 結束, Pages 不會把失敗的建置換上正式網址。
set -eu
cd "$(dirname "$0")/.."
origins=${ORIGINS_FILE:-deploy/origins.json}
node -e 'const [major, minor] = process.versions.node.split(".").map(Number); if (!(major === 22 && minor >= 18 || major === 24 && minor >= 12 || major > 24)) { console.error(`Node ${process.versions.node} is not supported; set NODE_VERSION=24 for Pages`); process.exit(1); }'
site=$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1], "utf8")).site)' "$origins")
app=$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1], "utf8")).app)' "$origins")
if [ "$site" = "$app" ]; then echo "deploy/origins.json still uses one origin; split site and app before publishing Pages" >&2; exit 1; fi
commit=${CF_PAGES_COMMIT_SHA:-$(git rev-parse HEAD)}
cd frontend
npm ci --no-audit --no-fund
VITE_SITE_ORIGIN=$site VITE_APP_ORIGIN=$app npm run build:public
# 版本識別：對照 Pages 部署紀錄與 git commit, 回退時確認目前上線的是哪一版
node -e 'require("fs").writeFileSync("dist-public/version.json", JSON.stringify({ commit: process.argv[1], branch: process.argv[2], builtAt: new Date().toISOString() }) + "\n")' "$commit" "${CF_PAGES_BRANCH:-local}"
echo "Built public site $commit for $site"
