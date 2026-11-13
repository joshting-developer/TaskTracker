---
name: tasktracker-test-environments
description: TaskTracker專案專用的測試環境與驗收分層約定：Claude／Codex各自的瀏覽器測試埠與隔離後端、截圖比對共用規則、測試環境限流、每張卡要跑哪些驗收。在TaskTracker跑瀏覽器檢查、開隔離MySQL環境、做視覺比對或決定驗收範圍前讀。
---

# TaskTracker 測試環境與驗收分層

這份是TaskTracker自己的環境約定（埠號、Compose專案名稱、哪些檢查算冒煙）, 其他專案用不到, 所以放在專案裡, 不放共用庫。通用的驗收原則還是看共用 `side-project-development-standards` 的 `references/verification.md`, 這裡只補本專案怎麼落地

## 為什麼有這份
2026-11-14 Josh和Claude發現, Codex和Claude花在驗收的時間比開發多：大家搶同一個5175測試埠、同一套截圖比對環境, 測試環境限流一跑多支就被擋, 每張卡又都跑完整回歸。這份把資源分開, 並說清楚每張卡要驗到哪裡

## 埠號與隔離環境

| 用途 | 前端埠 | 隔離後端埠 | Compose專案名稱 |
| --- | --- | --- | --- |
| 正式開發環境（Josh在用的主服務） | 5174 | 3002 | tasktracker |
| Claude的瀏覽器檢查 | 5198 | 13198 | tasktracker-claude-test |
| Codex的瀏覽器檢查 | 5199 | 13199 | tasktracker-codex-test |
| CI和沒有指定時的預設 | 5175 | 13029 | 依CI設定 |
| 截圖比對（共用一套） | 5195 | 13079 | tasktracker-codex-visual |

- 5174／3002是主服務, 只能拿來看畫面, 不能拿來跑會建帳號、改資料的檢查
- 瀏覽器檢查一律讀 `E2E_FRONTEND_PORT`（前端埠）和 `E2E_API_URL`（隔離後端）, 沒設定時才用5175。Claude跑之前設 `E2E_FRONTEND_PORT=5198`, Codex設5199
- 隔離後端用專案的 `docker-compose.mysql-test.yml`, 換自己的埠和專案名稱就好, 它已經允許5175、5198、5199三個來源

```sh
# Claude範例；Codex把claude換成codex、13198換成13199、5198換成5199
TASKTRACKER_TEST_PORT=13198 docker compose -p tasktracker-claude-test -f docker-compose.mysql-test.yml up -d --wait
cd frontend && E2E_ISOLATED=1 E2E_FRONTEND_PORT=5198 E2E_API_URL=http://127.0.0.1:13198 node tools/<檢查>.js
# 用完清掉自己的環境
TASKTRACKER_TEST_PORT=13198 docker compose -p tasktracker-claude-test -f docker-compose.mysql-test.yml down -v
```

- `E2E_ISOLATED=1` 是明確宣告「這是隔離環境」, 測試的共用輔助函式（fixtures/verification.js）才會自動用local666填驗證碼；沒設的話走畫面註冊的檢查會直接停下來
- 隔離環境的映像如果比較舊, 寄信範本（backend/resources）可能不是最新的, 註冊驗證信會出錯；這時另外多掛 `backend/resources`, 或先重建映像
- 埠被佔用時不要停別人的程序, 先確認是不是自己的；只清理自己開的Compose專案

## 截圖比對只有一套
- 核准過的基準只有一份, 兩個人同時跑一定會互相覆蓋, 所以只能輪流
- 一律用 `sh tools/visual-check.sh candidate｜prove｜compare`, 腳本自己有鎖, 有人在跑時會直接失敗, 不要硬拆鎖或重設別人的環境
- 跑之前先看 `docker ps` 有沒有 tasktracker-codex-visual 在跑, 有的話等對方跑完或用小待辦協調

## 測試環境限流
- 註冊、登入、驗證碼、重寄驗證碼的限流, 在testing環境可以用 `TASKTRACKER_AUTH_THROTTLE_SCALE` 放大倍數, 隔離測試Compose已經設成50；正式和local永遠是原本的次數, 有後端測試在顧
- 公開API每分鐘120次的限流不放寬, 因為有專門的檢查在測它

## 驗收分層
每張卡照這三層挑, 不用每次都跑全部

1. **這張卡自己的測試（每張都要）**：跟改動有關的後端測試（SQLite＋需要時隔離MySQL）、改到的前端檢查、這張卡新增的專屬檢查、build。有畫面改動照共用規範做視覺前後比較
2. **冒煙測試（每張都要, 幾分鐘內跑完）**：`cd frontend && E2E_ISOLATED=1 E2E_FRONTEND_PORT=<自己的埠> E2E_API_URL=<自己的隔離後端> npm run test:smoke`, 內容是網址解析、API client、composables、build, 加一支真瀏覽器流程（註冊＋驗證碼、建專案、切換每個側邊欄項目）
3. **完整回歸（打tag前, 或改到共用元件、路由、權限、登入流程時）**：後端全套（SQLite與隔離MySQL）、frontend/tools底下的全部瀏覽器檢查、截圖比對。打tag發版前一定要跑一次（TT-320）

- 跑出跟自己改動無關的失敗：記下哪支、停在哪一行、看起來是誰的範圍, 用小待辦交給負責的人, 不要為了它卡住自己的卡, 也不要寫成全部通過
- 回報時分清楚「跑了哪幾層、哪些過、哪些沒跑和原因」
