# 個人 API Token（TT-165）

2026-11-12。已交付個人PAT建立／清單／撤銷與純Bearer身分端點。**任務、專案、留言的公開REST尚未開放**，由TT-168／169接續；不要拿PAT呼叫既有Session RPC。

## 網頁操作

右上帳號選單 →「個人 API Token」。Token綁定**目前工作空間**，清單只顯示本人此工作區的憑證；同一人另一工作區需另建。管理入口不是Owner限定功能，一般有效成員亦可建立自己的PAT；管理者不能查看或撤銷別人的PAT。

填寫名稱、Scope與可選到期日：

- `read`：讀取；`write`隱含read，但不能因此越過本人的Project／資源／作者權限。
- 不填日期預設一個月（月底採no-overflow），最長兩年（閏日同理），不提供永久Token或延長。
- 指定日期在Asia/Taipei當日00:00到期；API輸出ISO8601 UTC。資料庫日期沿既有Laravel Asia/Taipei cast與連線時區契約，不先轉UTC再讓cast重複換算。
- 建立成功只在當次回應顯示一次原文；關閉或隱藏後不能找回。沒有寫入localStorage／sessionStorage或服務端原文快照。遺失就撤銷並另建。
- 已過期／撤銷保留在清單；過期不通知、不自動延期。撤銷可重複執行，無硬刪除。

建立請求須有UUID `requestId`。同一人重複使用已成功建立的requestId回409 `REQUEST_CONFLICT`，不再次回原文，也不產生第二個Token。這是刻意不同於一般卡片receipt的秘密策略：原文不進可重播快照。逾時先用**同一requestId**核對；若收到409，先查清單撤銷可能建立的憑證，再由使用者明確建立新請求。`requestId`不是Token名稱，名稱可重複；目前未訂數量上限或自動輪替政策。

## 已開放HTTP契約

[正式單一來源OpenAPI](../backend/resources/openapi.json)由匿名`GET /api/public/openapi.json`取得，僅列已交付的4個操作。後續卡片在同一份規格擴充，不能另生成第二份分歧規格。

| method／path | 認證 | 行為 |
| --- | --- | --- |
| GET `/api/v1/personal-tokens?page=1` | 人類Session | 本人目前Workspace，每頁20筆metadata，id倒序；page上限1,000,000，超末頁收斂 |
| POST `/api/v1/personal-tokens` | 人類Session＋CSRF防護 | 建立；name 1–60、abilities陣列僅read／write、requestId UUID、expiresAt選填YYYY-MM-DD |
| POST `/api/v1/personal-tokens/{id}/revoke` | 人類Session＋CSRF防護 | 撤銷；無有效本人此工作區Token回404 |
| GET `/api/public/v1/me` | **僅Bearer PAT** | 最小userId／workspaceId／abilities；不依賴瀏覽器目前Workspace，重新驗固定工作區成員資格 |

Session端點仍用既有Cookie與`/api/csrf-cookie`。建議帶`X-Workspace-ID`，與Session不符回409 `WORKSPACE_CHANGED`；header不能任意指定可操作的空間。Laravel13框架接受可信`Sec-Fetch-Site: same-origin`的來源驗證，既有前端仍傳CSRF Token；非瀏覽器無可信來源metadata又缺CSRF的寫入回419。未繞過原有CSRF或把PAT當Session。

建立成功範例（僅示意，不是真實Token）：

```json
{"ok":true,"data":{"token":{"id":"42","name":"CLI","abilities":["read"],"createdAt":"2026-11-12T00:00:00.000000Z","expiresAt":"2026-12-11T16:00:00.000000Z","status":"active"},"plainTextToken":"42|tt_<one-time-secret>"}}
```

列表不會包含`plainTextToken`、token雜湊或issuance_request_id。狀態為`active`／`expired`／`revoked`，撤銷優先顯示。所有管理／業務回應沿用`ok/data`或`ok:false/error:{code,message}`與no-store。

外部工具範例（`TASKTRACKER_URL`為實際服務位址、`TASKTRACKER_TOKEN`由安全的環境／秘密管理提供；不要寫入Git、卡片、程式碼或日誌）：

```sh
curl --fail-with-body \
  --header "Authorization: Bearer ${TASKTRACKER_TOKEN}" \
  "${TASKTRACKER_URL}/api/public/v1/me"
```

無效／到期／撤銷／失去Workspace資格回401；不把人類已登入Cookie當成Bearer成功。Scope不足回403。瀏覽器跨來源仍受既有Origin allowlist，沒有在這張卡開放任意CORS；一般外部server／CLI沒有瀏覽器Origin要求。HTTP在正式公開前需HTTPS，本地loopback僅供試作。

限流：建立20次／分鐘、撤銷60次／分鐘、身分120次／分鐘（框架預設辨識鍵；反向代理可信IP另在部署設定）。清單有SQL分頁但目前沒有額外限流。未宣稱這些值是正式容量SLO。

## 後端與資料安全

Sanctum4.4.0鎖定於composer.lock；沿用官方generateTokenString、SHA256儲存與findToken／Guard驗證，因workspace_id與issuance_request_id為NOT NULL，發行時同次INSERT填入，不先建立無scope Token再補資料。自訂PersonalAccessToken Model只擴充workspace／撤銷與write包含read；Sanctum guard不接受web Session fallback，且關閉它額外的csrf-cookie路由，保留專案自己的入口。

`personal_access_tokens`以User UUID FK、workspace FK、雜湊唯一、本人requestId唯一及owner/workspace/id複合索引隔離。Tokenable僅支援User模型，不支援任意polymorphic principal。撤銷以revoked_at保存；沒有TTL清理、原文保存、背景通知或last_used_at逐次寫入。即使以Model自動序列化也隱藏雜湊／requestId，HTTP另用明確metadata投影。

管理Controller只協調FormRequest／WorkspaceTransaction／PersonalTokens，不直接查DB。共用交易負責User→Workspace寫入鎖、SessionLease前後重驗、Context與X-Workspace-ID，與Claude已完成的TT-249邊界共用，未複製第二套交易。個人Token單Model及簡單join／subquery範圍由Service用Eloquent，不機械建立空Repository。

BearerTokenSession是獨立HTTP認證邊界，純Bearer入口移除StartSession、ShareErrorsFromSession與CSRF中介層，不建立Cookie或Session寫入。當次交易重新驗Token及其固定Workspace資格，回傳最小投影；這不代表已完成TT-166的完整通用操作稽核，或TT-168／169的Project資源授權／寫入端點。

## 資料存取八項審查

- 管理清單只需要id／名稱／能力／建立與到期日／狀態，沒有帳號全集、Workspace快照或原文。SQL按本人＋目前Workspace篩選、id倒序、LIMIT20／OFFSET；COUNT與深頁仍隨本人Token數量成長。
- 建立與撤銷是使用者操作，不加輪詢。列表開啟、翻頁與成功異動後讀取；隱藏元件後不做背景刷新。
- 每次建立在共用交易重驗資格；UUID唯一鍵與User鎖防同requestId競態，不在RequestReplay儲存秘密。撤銷是本人＋Workspace＋主鍵條件更新，不讀其他Token。
- 純Bearer身分只查單一Token／User與Workspace資格，停用last_used_at追蹤以免每次讀取多一筆寫入；沒有跳過權限或引入cache過期窗口。
- 測試含跨帳號、同帳號跨Workspace、缺／錯誤／到期／撤銷Token、失效成員、write含read／read不含write、20筆分頁、不能藉Bearer管理Token或呼叫RPC、原文與雜湊不出現在列表。
- 回傳大小受名稱60字／最多2個scope／20筆限制；未做正式HTTP p95、遠端MySQL或多worker容量聲明。

## 交付及未完成界線

TT-165完成的是PAT生命週期與可驗證的Bearer身分入口；TT-166的通用Token操作稽核、TT-168／169的專案／任務公開REST、TT-252完整文件站／Agent指南及TT-253全面規格CI、TT-254外部只讀文件建卡驗收仍依各卡處理。本輪不自建MCP、工具專屬整合或替使用者編排Agent。

增加migration為新Token表，不覆蓋／seed現有業務資料。生產回退不能直接drop表遺失已發行憑證；本機更新image與migration不等於正式雲端部署。秘密原文只有客戶端短暫記憶體，正式運維需避免反向代理／APM記錄Authorization與建立回應。

依據：[Laravel13 Sanctum官方文件](https://laravel.com/framework/docs/sanctum)、[官方HasApiTokens實作](https://github.com/laravel/sanctum/blob/4.x/src/HasApiTokens.php)（2026-11-12查證），產品規則依[API-first決策](api-first-platform.md)。

## 本輪驗證（2026-11-12）

- 隔離memory完整回歸：108 passed、32 skipped（原生MySQL類另跑），4645 assertions。
- 原生MySQL相關回歸：23 passed、678 assertions。PAT與規格最終5 passed、151 assertions，包含讀取不更新last_used_at。這些是正確性／隔離測試，不是效能p95或正式負載驗收。
- 真實Chrome＋隔離HTTP：帳號選單、建立原文一次、重開不洩漏、Bearer、撤銷401、未把原文寫入localStorage／sessionStorage；1440／768／390三尺寸無水平溢出。
- `npm --prefix frontend run build`通過；Pint僅14個新增PHP檔通過。
- 本機backend／queue／websocket載入新映像，PAT migration為batch2已執行；主庫未seed／reset。其餘服務維持，公開OpenAPI與正式JSON逐欄一致。

瀏覽器重跑命令：
```sh
E2E_API_URL=http://127.0.0.1:13029 \
PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH='/Applications/Google Chrome.app/Contents/MacOS/Google Chrome' \
npm --prefix frontend run test:tokens
```

需先啟動獨立tasktracker-perf-test測試Compose與編譯前端；工具固定拒絕其他API URL，不能改指向現用業務庫。截圖只存暫存目錄且不含原文，不提交Git。
