# 專案與任務標籤

TT-193, Season 0, OpenAPI 0.24.0

## 使用與權限

每個專案共用最多 200 個有效定義, 欄位為穩定 ID、名稱（40 字）、顏色與說明（300 字）。名稱不分大小寫去重。每張卡片最多 20 個, 選取不會改變卡片權限

- `issue.read`：讀取定義、卡片標籤與有界篩選
- `issue.write`：對有權限的卡片差量新增／移除標籤
- `project.tags.manage`：建立、修改與刪除定義；預設負責人／專案管理者擁有, Owner／Manager 經 `project.tags.manageAny` 取得跨專案能力

在專案側欄「標籤管理」編輯定義。卡片完整頁及編輯器提供「編輯標籤」, 草稿與卡片欄位分開儲存, 有其他未儲存內容時先完成該內容。列表顯示名稱, 超過三個以數量收合；完整頁顯示全部

## 關聯與刪除

改名保持 ID, 既有卡片顯示新名稱。刪除以停用定義加單一 SQL 移除關聯處理, 定義及既有卡片歷史快照保留, 不逐張改寫卡片版本或創造大量事件。刪除後可重新使用名稱, 新定義有不同 ID

定義變更增加專案 revision, 看板既有版本檢查會更新；不為每張受影響卡片增加 revision。外部增量同步應同時刷新標籤定義, 不能只依 Issue revision 推論定義未變

卡片差量套標才會增加該卡片 revision, 異動歷史保存當時名稱／色彩／ID。純增刪重播不重複事件；新增已存在或移除未套用的標籤不增加版本。停用或跨專案定義不可新增

## 介面

Session 使用 `/api/v1/projects/{project}`, PAT 使用 `/api/public/v1/projects/{project}` 前綴

| Method／路徑 | 用途 |
| --- | --- |
| GET `/tags` | page／perPage／search 有界列表, 預設 20、最多 100, 名稱及 ID 穩定排序 |
| GET `/tags/{tag}` | 單一定義與說明 |
| POST `/tags` | name 必填, color／description 選填 |
| PATCH `/tags/{tag}` | 部分更新定義, expectedRevision 必填 |
| DELETE `/tags/{tag}` | 刪除定義及關聯, expectedRevision 必填 |
| PATCH `/issues/{issue}/tags` | addTagIds／removeTagIds 差量, 各最多 20 個不重複 ID, 不可重疊 |

所有寫入需 UUID requestId, 修改／刪除及套標使用目前資源版本；有效 ACL 與 Token Scope 在重播前重新驗證。寫入回有界 ID／revision, 不附全集合

卡片列表、看板及增量查詢接受 `tagIds[]=id` 陣列, 必須全部符合, 篩選在 SQL 執行。列表／看板摘要只帶 id／name／color, 定義說明按需查 `/tags`；每頁以一次關聯查詢預載, 不逐卡取標籤

## 自動領卡

先讀定義說明及完整卡片, 再判斷是不是本次工作。Future／manual_only／暫不執行是排程限制, 不是 API 權限；不因 Token 可寫就自動開工。明確指派恢復的需求仍需核對使用者授權與依賴

尚未實作通用批次編輯、標籤自動化規則或依標籤提權

## 本輪驗證與發布

SQLite／隔離 MySQL 完整後端共 520 項、20,185 assertions 通過。R2 專用生命週期測試需獨立分組, 本次不以跳過代表通過。標籤定義 CRUD、差量套用、草稿保護、SQL 篩選及列表／看板／完整頁已用真實隔離瀏覽器操作；三尺寸共 15 張候選依 Josh 持續授權核准, 原比較參數下獨立比對零差異

共用 Skill 的 CLI 以隔離 PAT 實測定義 CRUD、說明、卡片套標、AND 篩選、看板篩選與刪除重播, 測後撤銷 Token。共用庫提交 bf23658；正式尚未部署, Season／Future 實際套標待整批 v1.0.0 上線後執行
