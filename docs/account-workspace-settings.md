# 帳號與工作空間設定

日期：2026-11-05，Asia/Taipei。

使用者已要求：頭像開啟帳號選單、顯示／編輯資料與登出，移除左下登出；工作空間可改名，權限入口應清楚。

本輪實作選擇：個人資料先修改顯示名稱（60字），Email唯讀；工作空間設定分一般／成員與權限。角色沿用固定permission組合，自訂角色、改Email／密碼、ownership轉移另案。成員角色可選manager／user；owner資格透過ownership permission保護，不在一般指派介面變更。Workspace管理權限影響全部專案；個別Project授權維持專案內成員頁。

| 動作 | 輸入 | 可信來源與保護 |
| --- | --- | --- |
| getProfile | 無 | Session本人；只回姓名、Email與版本 |
| updateProfile | name、expectedRevision、requestId | 固定本人，不接受前端指定帳號；更新SQL姓名與profile_revision |
| getWorkspaceSettings | 無 | Session空間；一般成員不回全員目錄 |
| updateWorkspace | name、expectedRevision、requestId | workspace.update；更新SQL名稱及settings_revision |
| setWorkspaceMemberRole | userId、roleId、expectedRevision、requestId | workspace.members.manage；同Workspace有效成員，保留ownership資格，不處理超過自己有效權限的授權；更新role及role_revision |

所有寫入在ApiExecution交易中重新取得Session與permission，讀重試結果前重新授權，requestId只回識別碼及版本。版本衝突保留輸入，需明確重載。未保存設定／邀請Email關閉或離開前確認，處理中禁止重複提交。名稱異動不改歷史留言／工時作者快照。

資料：users.profile_revision、workspaces.settings_revision、workspace_members.role_revision為整數SQL欄，預設0。既有業務DTO不新增版本欄，由專用設定API回傳；RecordStore一般寫入不覆寫這些欄位。升級不更動既有姓名、空間、角色與密碼；down只移除版本欄，重新up的版本從0開始。正式回退需停止寫入並重新開啟表單。

後端完整測試46項／896個斷言通過，包括本人／租戶邊界、SQL寫入、版本／重試、角色升降與ownership保護、降權重試拒絕及migration往返。前端流程及本機升級驗證進行中；僅本機功能，不宣稱正式SaaS或真人可用性驗收。
