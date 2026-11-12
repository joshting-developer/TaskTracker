# 公開查詢API（TT-168）

2026-11-12。正式OpenAPI升至0.3.0，11個path／12個operation（包含既有Session管理）；新增7個純Bearer GET。公開寫入尚未開放，TT-169接續，最終僅憑文件建卡驗收仍TT-254。

## 資源與使用方式

公開前綴`/api/public/v1`，所有查詢使用PAT的固定Workspace，`read`或含read的`write`能力；與本人當下permission及資源範圍取交集。沒有Cookie／CSRF，Session不可替代Bearer。有效Project資格每次重驗，不依role名稱、前端payload或上一個請求快取放行。

| GET | 共用用例／輸出 |
| --- | --- |
| /projects | ProjectReadService.index；SQL限本人可見Project摘要及本人permissions，不帶Issues |
| /projects/{project} | ProjectReadService.show；Project屬性／狀態與本人permissions、accessSource |
| /projects/{project}/members | ProjectReadService.memberOptions；有效Project成員或全專案可見者，只回id/name分頁 |
| /projects/{project}/statuses | ProjectReadService.statuses；最多20個實際type／label／color、設定順序，SQL只讀狀態欄位 |
| /projects/{project}/issues | IssueReadService.index；篩選／排序／分頁的摘要，不查描述、活動或工時 |
| /projects/{project}/issues/{issue} | IssueReadService.show；描述、類型／狀態／指派／到期、Epic／父卡／關聯及最新revision |
| /projects/{project}/issues/{issue}/comments | IssueReadService.comments；該卡留言分頁，不讀其他卡或活動 |

列表摘要不是可直接覆寫的編輯基準。先以`identifier`精準查卡號取得Issue ID，再讀詳細及revision；目前沒有公開PATCH或POST，後續寫入沿TT-169契約。`estimateMinutes`在Epic為所屬卡加總，`spentMinutes`只有有效工時資格時提供，不應把唯讀衍生值無條件回填。留言／歷史不附在詳細；本卡只開留言讀取，未新增公開歷史／工時明細。

列表預設20、最大100，SQL搜尋／篩選後計total及分頁。頁碼超出回空items；此行為不同於個人Token管理清單。Project排序updatedAt降序＋publicId；成員userId；留言position＋id。Issue sort沿共用查詢：updated／priority／due，固定publicId決勝，不重新在客戶端排序。條件與上限見[正式匿名契約來源](../backend/resources/public-api-contract.md)，Schema、參數及假資料範例見[唯一OpenAPI](../backend/resources/openapi.json)。

狀態開放自訂，不能硬編enum；現有設定2～20個且open／closed必備。狀態端點最多查21筆，若歷史資料超20回409 CONFLICT而非靜默截斷。單卡關聯超過200亦409。這兩種錯誤都不是版本衝突，需整理資料，不能盲目重送。

## 純HTTP範例

`tools/public_query_example.py`只使用Python標準HTTP、不匯入TaskTracker服務／CLI／Vue／原始碼，沒有CookieJar。用已授權的私有環境PAT與部署Base URL執行：

```sh
BASE_URL=https://your-tasktracker.example \
PROJECT_ID=your-visible-project-id \
ISSUE_IDENTIFIER=EX-1 \
python3 tools/public_query_example.py
```

`TASKTRACKER_TOKEN`需預先以私有方式設定；不要把原文貼進shell歷史、卡片或Git。省略PROJECT_ID時會取第一頁第一個可見專案；不符合卡號時拒絕，不模糊換租戶。工具依序查身分、專案列表／詳細、狀態、成員、精準卡號、最新詳細與留言，僅輸出驗證結果、revision與筆數，不印PAT／描述／留言／個資。這是查詢範例，不代表TT-254整套建卡流程已完成。

## 存取範圍審查

1. 呼叫端只需可見Project、指定Project選項／Issue摘要、單卡詳細或指定活動；每端點只處理一個責任，沒有Workspace bootstrap。
2. 分頁上限100，穩定排序；SQL先套Workspace／Project及有效資格。狀態欄位最多21，關聯最多201做超限檢查，回傳上限20／200。
3. 篩選在SQL執行，identifier不是模糊search。深OFFSET／COUNT仍隨資料量增長，沒有固定延遲保證。
4. 單卡詳細／留言按需讀。列表不讀description、issue_comments、audit_events、time_entries；狀態不讀Project描述／Issues，測試有SQL及結果範圍證據。
5. 共用現有Application Service／Repository／Resource，只增加薄Bearer controller。FormRequest不接受actor／Workspace作可信值，未知query忽略。
6. 每HTTP都重驗Token／Workspace與當次Project資格；新公開GET共用每IP120／60秒配額。沒有新增輪詢／批次全Workspace查詢／Redis權限cache；讀取不追加來源audit或last_used_at寫入。
7. 測試加入另一Project大型描述、同租戶多卡、另一Workspace、Project指派移除／Reader、成員停用與撤銷；檢查資料投影、SQL有界、Schema與無Session，而非只驗查詢數。
8. 本輪不修改SPA呼叫端，因為SPA保持Session API；不是要求前端改用PAT。舊效能／資料存取改善不因新增公開介面重新宣稱完成。這是拓展共用安全查詢，不是效能p95或全庫合規證書。

## 交付範圍

未新增資料表或修改既有業務資料。公開API仍無寫入、刪除、Webhook或MCP；第三方文件站／CI另依TT-252／253。HTTP錯誤、版本、到期／撤銷、重試及安全500沿TT-165～167，新增不可見Project／Issue404、驗證400及有界集合超限409均同步正式機器規格。

## 本輪驗證

- memory完整回歸124 passed／45原生MySQL類略過（5463 assertions）；原生MySQL查詢／Token邊界10 passed（337 assertions）。後續最小狀態投影與超限分支再以最終受影響測試驗證，非原完整回歸的計數。
- 最終受影響19 passed、950 assertions，含正式OpenAPI範例／實際HTTP Schema、memory＋MySQL查詢／撤權、有界資料與狀態超限。
- 無Cookie的獨立Python HTTP客戶端：身分→可見Project列表／詳細→狀態／成員→identifier→單卡revision 2與描述／關聯→留言，全部通過；Token只作環境輸入，測完即撤銷。這是查詢子流程，不是TT-254全部建卡驗收。
- 既有PAT真實Chrome流程及三尺寸通過；無前端產品改動，只將spec path數斷言更新。Pint受影響PHP檔與官方OpenAPI結構檢查通過。
- 本機載入新版backend／queue／websocket，公開JSON與Markdown對照正式來源；不新增migration、不seed／reset現用主庫，不推送遠端。

所有測試使用隔離tasktracker-perf-test、tasktracker_test帳號／schema；沒有拿現用資料做壓測或將memory耗時冒充HTTP p95。
