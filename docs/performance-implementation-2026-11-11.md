# 效能改善實作

日期：2026-11-11。使用者已授權實作效能看板與API分責；先前複審的「只建卡」為歷史範圍。沿用既有Session／CSRF／permission＋scope／revision／requestId，不新增PAT或工具專屬整合。依階段完成驗證與commit，未通過驗收的卡不結案。

## A：健康探針與資料讀寫成本

- `/api/health`明確排除Session／Session錯誤共享及request-forgery middleware，只處理無狀態GET存活資訊；業務與CSRF端點仍保留原middleware。測試確認零SQL、無Set-Cookie。
- RelationalRecords只在同一入口物件快取可信Workspace內部ID；不跨請求、不快取有效權限。公開ID與成員對照仍在每次完整讀取／保存重建，避免fixture或同交易其他入口新增資料後讀到舊映射。
- 看板驗證一次SQL投影selected IDs的Project，不逐卡載入留言／歷史；錯誤Project／不存在ID保留原子拒絕。重新命名保留既有pivot主鍵。
- 留言、關聯、看板收錄、audit streams改成差量同步；只insert新列、delete移除列、update實際更動欄位。JSON物件鍵順序不算內容異動，陣列順序／型別保留。不刪歷史或放寬revision。
- 填時以同工作區／作者／工作日／有效紀錄SQL SUM替代all(timeEntries)，修改排除原紀錄；當次資格、每日1440分鐘與穩定User／Workspace鎖仍由既有交易入口保護。

`StorageCostTest`以12卡與100則舊留言驗證：一次參照SQL、追加留言一條寫入、改狀態零留言寫入、舊主鍵保留。完整memory回歸68項／2511 assertions，5項原生MySQL案例明確skip；另用獨立MySQL Compose完整73項／2578 assertions通過；原生JSON鍵順序與留言主鍵補測2項／23 assertions通過。不連遠端主庫。這些是操作成本／正確性證據，不宣稱HTTP p95或真人體驗已全面改善。

初始改動期間曾嘗試沿用更多ID映射，隔離Seed／匯入測試抓到跨入口新增後的過期映射，已撤回；只保留穩定Workspace ID。批次欄位使用明確alias，避免兩個public_id的投影名稱衝突；留言主鍵測試以一致ID排序比較。未降低原有斷言。

## 後續階段

分責查詢API與分頁、前端按頁面載入與有界同步、Todo／報表／月曆、正式runtime／量測與bundle，依看板逐項續作。未在本段完成的卡仍未結案；整體尚不能稱為已解決全量Workspace。

## B：新增分責查詢邊界

新增v1 Project分頁／屬性、Issue SQL分頁／詳細、留言與異動分頁，既有RPC保留。Service重用有效ProjectAccess；列表不讀Issue描述或子活動，活動只用父列參照；Project列表同快照取得能力／負責人摘要，Resource不查DB。專用Request／readonly條件與分頁資料隔離HTTP，契約見query-api-v1.md。Reader、私有Project、跨Project ID、撤權、X-Workspace-ID與literal搜尋／20筆SQL分頁／活動按需測試通過。一般列表／單卡查詢只取操作者本人的Project指派與最小Project參照，不為單人讀取載全員配置；2026-11-12複審再縮限為指定Project或當頁ID，不附全員memberIds；成員選項待獨立有界端點。前端尚待接線，TT-180／214／215仍進行中，不提前結案。

## 2026-11-12 資料存取規範複審

使用者要求的八項審查已加入AGENTS，並明確授權修改過量API契約。複審修正Project授權全量參照與登入context附帶歷史，新增1→36 Project範圍成長／5筆分頁／回應不變測試。隔離MySQL完整78／2803與最終受影響9／414通過。遠端正常讀取摘要仍約2.3～2.6秒，未取得SQL分段與p95；前端全量載入及其他P1尚未完成，整體驗收未通過。見[八項複審](data-access-review-2026-11-12.md)。
