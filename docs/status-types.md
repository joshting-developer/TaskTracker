# 狀態 Type 與結案

更新：2026-11-06。依使用者要求取代舊category分類與列表分類篩選。

狀態設定左側為英文Type，右側為顯示名稱，另可調整顏色與順序。使用既有DTO `id`／資料庫`project_statuses.code`作Type，`Issue.state`仍引用此值，內部FK `state_id`不變；不另建同義欄位。已儲存Type在介面唯讀，改名僅改label。

| Type | 新專案顯示名稱 | 行為 |
| --- | --- | --- |
| open | 待規劃 | 必備，預設建立位置，可改名／色／順序，不可移除 |
| inprogress | 進行中 | 一般狀態，可依使用情況移除 |
| closed | 已關閉 | 必備，唯一結案狀態，可改名／色／順序，不可移除 |

其他如done／review／testing皆為一般中間狀態，不會因顯示名稱或舊category被當成結案；取消的歷史狀態亦不再自動視為結案。一般Task／Bug可直接關閉／重開；Epic沿用子卡檢查，所屬卡片須closed後才能closed，子卡重開不自動重開Epic。實際工時與ACL不變。

`updateStatuses`輸入仍是`projectId / expectedRevision / requestId / statuses`；每個狀態只收`id / label / color`。分類欄位已停用，傳category會回INVALID_INPUT，避免舊客戶端誤用；請重新整理客戶端。新Type以小寫英文開頭，僅小寫英文、數字、底線，最多40字；既有歷史ID不強制改寫。ID／label不可重複，2–20個狀態（舊上限12，容納既有12個再補兩個必要狀態），使用中的狀態不可刪除。刪除未使用的自訂Type再新增新Type不會改寫歷史事件。權限／revision／防重複提交維持原契約。

任務列表與看板篩選直接勾選多個狀態，狀態之間OR，與類型／成員等條件AND。空選擇代表所有狀態，收合後仍顯示已套用名稱；取消選擇可清除。舊「尚未結案／已完成」分類條件移除，情況僅保留逾期與未指派。只有closed免除逾期判斷。

升級migration `2026_11_06_000000_require_open_closed_statuses`逐專案補缺少的open／closed，保留所有原狀態ID、任務、外鍵、卡片revision、歷史及工時；done不轉closed。設定版本增加並留下設定事件，使舊表單衝突而不覆蓋；重跑冪等。若預設名稱與舊名稱衝突，新名稱附type，如「待規劃 (open)」。舊資料匯入也補齊；缺少狀態定義的舊版本先採歷史預設對照，不套用新預設造成FK遺失。

DB原category欄位保留供舊schema相容，不回傳或接受、不用於授權或結案；後續寫入以type衍生相容值。啟動前備份SQLite，再執行`docker compose exec backend php artisan migrate --force`。此資料migration回退不刪新增狀態，避免刪除已使用狀態；回到舊程式時新closed值仍可讀，但結案語意以舊版規則為準，正式回退應配合原備份。無需重設seed。
