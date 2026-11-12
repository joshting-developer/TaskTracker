# TaskTracker HTTP API 契約 v1

規格版號：0.2.0；更新：2026-11-12。正式機器契約：[OpenAPI](./openapi.json)。本文是版本／使用規則，OpenAPI是唯一端點及Schema來源；不另維護一份規格。本文與規格可匿名取得，不代表業務資料可匿名存取。

## 已開放能力與入口

Base URL是你實際部署TaskTracker的origin。公開Bearer前綴為`/api/public/v1`，目前**只有GET /me**。不要以PAT呼叫Session RPC。公開專案、卡片、留言讀寫尚未交付；不提供刪除、MCP或工具專屬介面。

| Method／path | 認證／用途 | 限流 |
| --- | --- | --- |
| GET /api/public/v1/me | Bearer PAT；固定Token Workspace的本人識別及能力 | 每client IP 120次／60秒 |
| GET /api/v1/personal-tokens | Session；本人目前Workspace憑證metadata | 本版未設路由限流 |
| POST /api/v1/personal-tokens | Session／CSRF；建立憑證、一次顯示原文 | 每使用者20次／60秒，未登入以IP計 |
| POST /api/v1/personal-tokens/{token}/revoke | Session／CSRF；撤銷自己的憑證 | 每使用者60次／60秒，未登入以IP計 |
| GET /api/v1/personal-token-audits | Session；本人目前Workspace成功寫入來源 | 本版未設路由限流 |

匿名發現入口`GET /api/public/openapi.json`回傳OpenAPI物件，`GET /api/public/contract.md`回傳本文Markdown；它們沒有業務envelope、不建立Session、不讀業務資料。其他操作預設回JSON。

## 認證、Scope與工作區

使用`Authorization: Bearer <PAT>`；不要放query、URL或log。PAT由使用者在帳號選單「個人 API Token」建立，原文只呈現一次，伺服器只保存雜湊；一個PAT綁定一個Workspace。預設一個月（月底no-overflow），最多兩年，無永久／延期；指定日期在Asia/Taipei當日00:00到期。撤銷、到期或資格失效回401。

`read`僅讀取；`write`包含read，但實際可做的事仍是Token能力∩使用者當下permission∩Workspace／Project與資源／作者條件。Role只是權限映射。更換網頁目前Workspace或送X-Workspace-ID不會改變PAT綁定，不接受用payload自稱的actor／role取得資格。Bearer入口不接受Session替代、不需要Cookie或CSRF。

Session管理沿用網頁登入與CSRF機制；變更操作需符合Laravel可信同源或有效CSRF驗證，非瀏覽器管理工具應傳X-CSRF-TOKEN。建議帶X-Workspace-ID防止跨分頁切換後誤操作，與Session不符回409。PAT不能增發或管理另一個PAT，也不能取得其他使用者的Token或來源紀錄。

本版不啟用跨站瀏覽器CORS。帶不受信任Origin或Sec-Fetch-Site: cross-site會403；同源前端與具有連線權限的伺服器／CLI使用標準HTTP。取得文件並不讓外部工具自動取得網路能力或操作授權。

## 回應、錯誤與資料上限

業務成功為`{"ok":true,"data":...}`；失敗為`{"ok":false,"error":{"code":"...","message":"..."}}`。用error.code判斷分支，不依賴可能調整的繁體中文訊息。資料時間戳為ISO8601 UTC；工作日／到期日語意另採Asia/Taipei。客戶端應容忍未知回應欄位，不能把列表metadata當可編輯的完整資料。

| HTTP | error.code | 處理 |
| --- | --- | --- |
| 400 | INVALID_INPUT | 修正JSON、欄位、日期或page；不是422 |
| 401 | UNAUTHENTICATED | 停止重試，檢查Token／到期／撤銷／成員資格或Session |
| 403 | FORBIDDEN | 停止重試，檢查來源、Scope與有效權限 |
| 404 | NOT_FOUND | 不存在或不可見；也用於未開放路徑／版本 |
| 405 | METHOD_NOT_ALLOWED | 改用受支援method；Allow列支援方法 |
| 409 | WORKSPACE_CHANGED／REQUEST_CONFLICT | 依錯誤分支處理，不盲目產生另一個requestId |
| 419 | CSRF_EXPIRED | 僅Session變更；重新取得有效Session／CSRF後核對原請求 |
| 429 | RATE_LIMIT | 至少等待Retry-After秒再重試 |
| 500 | INTERNAL_ERROR | 公開Bearer非預期錯誤，回應不含stack／SQL／秘密；稍後核對後再試 |

每個端點可出現的業務錯誤以OpenAPI該operation為準；404／405也可能在路由分派時發生。現在沒有公開卡片寫入，所以REVISION_CONFLICT目前不是已開放Bearer端點的回應；既有卡片版本規則由後續寫入API承接。

Token與來源清單只讀本人目前Session Workspace，固定每頁20筆、id倒序。page為1～1000000整數，預設1，不接受perPage。**Token清單超出範圍回最後一頁；來源清單超出範圍回空items**。data包含items/page/perPage/total/lastPage。COUNT／OFFSET仍會隨資料量成長，沒有固定延遲承諾；不需要定期刷新清單。Token建立需最多50000 bytes的JSON物件，未知欄位忽略且不授予任何權限。

## 限流與重試

上表每個受限操作各自計數，不與其他操作共用配額。公開v1目前依client IP計數，因此同IP多個工具會共享120次；Token管理依登入使用者計數，不依Token原文作cache key。失敗請求也可能消耗配額，429可先於認證錯誤發生。反向代理必須正確設可信代理，不能信任任意X-Forwarded-For偽造IP。

受限操作的成功回應提供X-RateLimit-Limit與X-RateLimit-Remaining；429另提供Retry-After（秒）及X-RateLimit-Reset（Unix秒），保留JSON錯誤envelope。其他失敗不保證帶剩餘配額Header；沒有公開負載／可用性SLA。不要以併發或換Token規避限流。

- GET可在暫時網路錯誤後有限重試，採退避；401／403不自動重試。
- PAT建立必填UUID requestId。重試同一requestId若已成功，回409 REQUEST_CONFLICT，**不重播原文、不再建立一個Token**。逾時後先核對清單並撤銷可能建立的憑證，再由使用者明確決定新請求；不要自動換UUID重送。
- 撤銷沒有requestId／revision欄位；重試同一Token是200，不重複來源紀錄。Token不存在／非本人／不屬於目前Workspace回404。
- 不將timeout當作失敗未寫入。後續公開卡片寫入將沿用既有requestId receipt及revision規則，相關範例隨實作同卡交付，不以現有PAT接口虛構建卡能力。

## 版本與棄用

URL的v1是API主版本；OpenAPI info.version是這份契約的發布版號，與OpenAPI標準版本3.1.0不同。相容新增端點或欄位可在同主版本更新文件；刪除／更名必填欄位、改變資料或冪等語意等破壞性變更需另開主版本或明確遷移流程。未知版本404，不回退到另一版本。

本版沒有棄用中的公開操作，因此不發Deprecation／Sunset Header。未來棄用時需在同份OpenAPI標deprecated、公布替代操作與停止日期，並在已公告日期前保留原版本；日期與支援期間由實際發布決定，不憑空承諾固定天數。本機開發版不代表正式部署或SLA。

## 可執行的目前最小範例

只將BASE_URL設為你自己的部署origin、TASKTRACKER_TOKEN設為私有環境值。以下不需要Cookie，也不會輸出Token：

```sh
curl --fail-with-body "$BASE_URL/api/public/openapi.json"
curl --fail-with-body "$BASE_URL/api/public/contract.md"
curl --fail-with-body -H "Authorization: Bearer $TASKTRACKER_TOKEN" \
  "$BASE_URL/api/public/v1/me"
```

OpenAPI中的Token字串是明確的假值，不可登入使用。規格與本文不內嵌真實帳號、Cookie、內部主機或PAT。
