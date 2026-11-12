# 公開REST契約交付（TT-167）

2026-11-12。已交付「目前可用能力」的正式OpenAPI 3.1、匿名Markdown契約與實際回應驗證。**沒有實作公開專案／卡片讀寫**；TT-168／169依下方目標矩陣接續，不因規格完成而結案。

## 單一來源與發現入口

- 正式端點／Schema／範例：`backend/resources/openapi.json` → `GET /api/public/openapi.json`。
- 版本、認證、限流、錯誤、分頁／重試與棄用規則：[正式Markdown來源](../backend/resources/public-api-contract.md) → `GET /api/public/contract.md`。
- 原有[分責查詢契約](query-api-v1.md)描述Session呼叫；公開Bearer須走TT-166邊界，不能只替既有RPC加Token就對外開放。

正式spec只含5個可用operation：Bearer身分、Session Token清單／建立／撤銷及本人來源清單。匿名發現路由不啟動Session或查業務資料。Markdown是規則解說，不是第二份機器Schema；沒有列未完成路由或內部伺服器。Swagger UI與完整開發者文件站尚未製作，TT-252承接；CI及獨立文件導向客戶端分屬TT-253／254。

## TT-168／169承接目標（尚未可呼叫）

以下是本卡收斂的REST設計目標，**不是正式OpenAPI或已部署路由**。實作時須在同一卡加入實際Schema、範例與Contract Test；不能先把這些path加入正式規格。

| 卡片 | Method／相對公開v1前綴 | 資料範圍／有效權限 |
| --- | --- | --- |
| TT-168 | GET /projects | SQL先限本人可見Project，再分頁；不帶Issues全集 |
| TT-168 | GET /projects/{project} | 單Project metadata及本人capabilities；project.read |
| TT-168 | GET /projects/{project}/members | 有界id／name候選，不回email或全Workspace成員；project.read |
| TT-168 | GET /projects/{project}/statuses | 單Project固定type／顯示名稱／顏色／排序；project.read |
| TT-168 | GET /projects/{project}/issues | 有界摘要，identifier可精準查卡號；issue.read |
| TT-168 | GET /projects/{project}/issues/{issue} | 單卡詳細與revision，不附活動全集；issue.read |
| TT-168 | GET /projects/{project}/issues/{issue}/comments | 單卡留言分頁，issue.read |
| TT-169 | POST /projects/{project}/issues | requestId、有限建立欄位；issue.write |
| TT-169 | PATCH /projects/{project}/issues/{issue} | revision／requestId、僅修改明確提供欄位；issue.write |
| TT-169 | POST /projects/{project}/issues/{issue}/comments | requestId及留言內容；沿用既有留言／版本規則，不能任意新增另一套規則 |

Epic／父卡／雙向關聯透過卡片PATCH的明確欄位承接，不能把省略的關聯集合當成清空。相關卡須逐資源檢查同Workspace／Project與有效權限，沿用既有原子更新及版本保護。成員／狀態列表是否可直接沿用Project詳細投影，實作卡可依最少資料原則調整URL，但須同步此矩陣與正式契約；不是要求增加重複查詢。

列表優先沿用現有查詢的page／perPage（預設20，上限100）、穩定排序、search上限200及identifier精準查詢。實際支援filters、欄位及最大集合須由各實作卡明列，不能照搬無關的Workspace內容。詳細／留言／歷史／工時分責，未支援的工時、看板、Todo、刪除及Webhook不暗中擴充。

公開寫入以路徑提供目標資源，不接受payload任意覆寫actor／Workspace。採既有camelCase、ok/data、revision／requestId、同交易來源與afterCommit事件。對既有卡片的更新必須帶當前revision；衝突409後重讀並由客戶端決定，不自動覆蓋。相同requestId與相同內容沿用既有receipt；不同內容不能以相同key重播。**PAT建立是秘密例外：重送409、不重播原文**，不能把它誤套到卡片建立。專用結果不得夾整個Workspace／活動全集；TT-169需明確驗證回傳與receipt容量。

## 本卡實際修正

- 三個受限操作改具名Limiter，避免建立／撤銷與其他操作誤用同一計數。公開身分維持120次／分鐘、IP範圍；管理建立20／撤銷60、登入使用者範圍。
- API HttpException保留Retry-After、X-RateLimit-*與Allow標頭；429仍回原有JSON envelope。
- 公開v1非預期錯誤即使debug開啟仍為安全500 INTERNAL_ERROR；未知版本404，錯method405 METHOD_NOT_ALLOWED＋Allow。
- 正式規格移除不可能或未設定的狀態／限流宣告。來源拒絕回應補no-store及nosniff；相容未改動的Session業務契約。
- 沒有增加SQL／列表查詢／輪詢或權限cache。文件入口是固定檔案，測試驗DB讀取0筆；限流使用既有具namespace Cache，未flush任何共享服務。

## 驗證工具與來源

- 官方結構Schema固定原文與Apache-2.0授權在`backend/tests/Fixtures/openapi/`，不修改動態參照。Python jsonschema處理官方結構；PHP Opis標準模式處理每operation範例與真實HTTP的資料Schema，兩者分工，不自製Schema子集。
- Python依賴固定在`tools/requirements-openapi.txt`。本輪以Python3.14專案私有venv驗證，不安裝至全域：

```sh
python3 -m venv .local/openapi-validation
.local/openapi-validation/bin/pip install -r tools/requirements-openapi.txt
.local/openapi-validation/bin/python tools/check_openapi.py
```

- `PublicApiContractTest`驗官方Schema所描述的實際成功／錯誤回應、範例、獨立限流、429 Header、無Session文件及安全500；TokenSpecificationTest驗route／operationId／security。
- `E2E_API_URL=http://127.0.0.1:13029 python3 tools/public_api_http_check.py`僅允許隔離服務，實測真HTTP的限流持續性與Retry-After；不要將壓限流測試改打現用主庫。
- [OpenAPI 3.1官方規範](https://spec.openapis.org/oas/v3.1.0.html)、[Laravel限流](https://laravel.com/framework/docs/13.x/routing#rate-limiting)、[Opis Validator](https://opis.io/json-schema/2.x/php-validator.html)，查證日期2026-11-12。

本卡沒有宣稱已完成CI、第三方只憑文件建卡、正式負載或部署驗收；這些分屬後續卡。

## 本輪驗證結果

- memory完整回歸119 passed／40原生類略過（5169 assertions）；原生MySQL Token／來源／撤權10 passed（262 assertions）。
- PublicApiContractTest＋TokenSpecificationTest：7 passed、418 assertions，覆蓋每operation成功與錯誤Schema、所有正式範例、HTTP status／Header、獨立限流與安全500。
- 固定官方結構Schema離線通過；刪除info.version的負向規格確實拒絕。不把PHP解析器對官方dynamicRef的不適用當作規格錯誤或修改官方檔案；Python處理結構、PHP處理實際資料Schema，均為標準驗證器。
- 真實Chrome PAT流程及三尺寸通過；隔離真HTTP持續性限流401→429、Retry-After與rate headers、匿名發現無Session均通過。
- Pint本卡6個PHP檔通過；bootstrap只局部修正錯誤Header／500處理，未格式化整庫。沒有前端產品程式變更，未把build當作流程驗收。
- 本機backend／queue／websocket載入新版，正式JSON與Markdown逐內容比對；本輪無新增migration、無seed／reset主庫，未推送／正式部署。

這些是契約及正確性證據，不是HTTP p95／負載或第三方建卡驗收。TT-168／169／252～254維持後續範圍。
