# 公開API實際操作確認（TT-265）

2026-11-12。使用者要求確認AI整合/API看板交付後能否供工具操作。結論：**日常卡片工作已可依公開文件與PAT執行；現有自我追蹤CLI仍未遷移，公開能力也未涵蓋所有Web功能。**

## 即時現況

- 執行中及原始碼OpenAPI均為0.9.0，共19 operations（15個公開Bearer業務操作、4個Session Token／稽核管理操作）。
- 核心TT-165～173、176、252～254已closed；TT-3總覽仍open，TT-174／175 Webhook及TT-177選配外部聯調仍deferred。不能將「核心完成」說成整板每張都已實作。
- 匿名文件`/api/public/docs`、OpenAPI`/api/public/openapi.json`、Agent原文`/api/public/docs/agents.md`可使用。以目前部署origin作Base URL，Token只放Authorization header。

## 實際主服務工作流程

以Codex自己的帳號與權限建立臨時write／read PAT；Session只用於Token管理及既有工具領卡。純標準HTTP公開客戶端依OpenAPI operationId找路徑，不傳Cookie／CSRF、不匯入後端服務。

本次真實追蹤卡TT-265由公開POST建立並掛TT-3，只修改本卡，不拿現有業務卡片測試：

| 操作 | 實測 |
| --- | --- |
| GET身分／Project／狀態／成員 | 本人、固定Workspace、TaskTracker可見、open／inprogress／closed及codex指派候選正確 |
| POST建立與同requestId重送 | 同一結果、只建立一張卡 |
| identifier精準列表／GET單卡 | 能找到ID並讀到最新revision |
| PATCH指派／進行中及實際內容變更 | 成功，伺服器回傳最新revision |
| 舊revision寫入 | 409 REVISION_CONFLICT |
| read PAT寫入 | 403 FORBIDDEN |
| 留言及同requestId重送 | 成功，原結果重播且留言僅一筆 |
| 有界增量 | items／nextCursor／syncUntil可讀，本次限定TT-265，未掃描整個Workspace |
| 關閉及讀回 | closed確認成功；為補驗與報告階段暫恢復inprogress，交付後再closed |
| 憑證／Session | Bearer回應無Set-Cookie；臨時PAT全部撤銷，撤銷後GET身分401 |

第一輪驗證曾錯將無內容變更PATCH視為一定遞增revision；實際無變更未推進版本。已改為先做實際內容變更、確認revision推進後再驗409，沒有把測試假設錯誤列成產品缺陷。客戶端應採回傳／讀回的revision，不自行加一或以本地猜測取代。

## 新能力的獨立MySQL補驗

只在專用Compose `tasktracker-readiness-20261112`、13059、tasktracker_test帳號／schema、tmpfs執行以下測試，不操作現用MySQL／Redis／資料卷：

```text
MySqlPublicExternalRefsTest
MySqlIssueChangesTest
MySqlPublicWritesTest
MySqlPublicQueriesTest
23 passed, 808 assertions
```

覆蓋externalId大小寫／Project隔離、upsert版本與重播、批次逐項失敗／savepoint／createOnly補跑、結構與read拒寫、externalRefs、水位緩衝、來源參照／重播不重複稽核、兩個Token回音與Web改寫、既有讀寫／cursor契約。原生測試由Laravel HTTP測試入口進真MySQL；沒有宣稱所有新能力都在真實主庫批次實跑。專用容器、network及secret volumes已清理。

本輪未重跑全庫／瀏覽器或正式部署驗收，未實測跨使用者回音隱私（TT-176原交付也明列此限制），不是無缺陷或容量／p95保證。

## 可依賴範圍及後續

- 現在可以：外部工具找Project／候選，查／建／改／指派／關閉卡片、留言與進度回報、版本保護與冪等、增量讀取；另有externalId upsert、最多50項批次與來源參照。
- `tools/tasktracker.py`目前仍使用Session／CSRF／內部查詢與RPC。若後續要讓日常領卡／留言／結案一律走PAT，需另做CLI認證與操作移轉、私有Token設定及驗收；本輪只確認差異，未自行改工具或保存長期PAT。
- 公開規格沒有看板管理、工時、小待辦及所有Web設定API。用API「指派／改狀態」可影響既有看板顯示；「建立／編輯看板與收錄」則未公開。
- PAT管理與本人稽核仍Session入口；不能把15個公開業務操作與4個管理操作全部視為Bearer可呼叫。
- 同步水位預設10秒緩衝，延遲更長或倒填時間仍可能漏過；外部鏡像需按id／revision去重、避免篩選造成離開範圍漏判並定期全量對帳。沒有刪除／封存或tombstone，不當作無損刪除changefeed。
- 本地localhost實測不證明外部平台能連進來。部署後的網路可達性、HTTPS及使用方Token供應要另驗；MCP、工具連接器與模型工作流未內建。

此輪不開始未授權的後續功能或恢復整板連續開發。驗證與限制已寫TT-265，後續以正式文件、當前權限與即時卡片狀態操作。
