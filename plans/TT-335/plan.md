# TT-335 驗收流程優化：Agent專屬測試埠、驗收分層與測試環境放寬限流

## 目標
讓Claude和Codex可以同時跑瀏覽器檢查不互相佔埠, 隔離環境連續註冊不被限流擋, 並說清楚每張卡要驗到哪一層

## Josh決定（11/14）
- 正式5174, Claude 5198, Codex 5199；寫成專案專用Skill
- 驗收分層、測試環境放寬限流由Claude做；測試前置步驟共用化留給Codex

## 做法
- frontend/tools寫死的5175全部改讀 `E2E_FRONTEND_PORT`（沿用既有寫法, 預設5175給CI）, 文件測試埠白名單加5198／5199
- 隔離後端約定13198／13199, 兩支Python檢查白名單加入；測試Compose允許5198／5199來源
- 帳號相關限流（註冊、登入、驗證碼、重寄）乘上 `auth_throttle_scale`, 只有testing生效, 測試Compose設50；公開API限流不動
- 新增 `npm run test:smoke`
- 新增 `.agents/skills/tasktracker-test-environments`, AGENTS和skills/README加入口

## 風險
- 截圖比對只有一份基準, 不能分Agent, 只能輪流（腳本已有鎖）
- 帳號限流原本用同一個計數, 保持原寫法只改數字, 倍數1時跟原本一模一樣
