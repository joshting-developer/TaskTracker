# TT-241 工作區設定與成員目錄拆分

日期：2026-11-12。承接已完成TT-242；本卡開始前已移inprogress，驗證完成後獨立提交／更新狀態。

## 實作與契約

一般設定、成員目錄及角色選項各自GET：

| API（/api/v1） | 欄位、資格與時機 |
| --- | --- |
| /workspace/settings | workspace的id／name／revision；workspace.read。進設定或明確重載，不附名單或角色 |
| /workspace/members | id／name／email／roleId／revision／canChange；workspace.members.read；只開成員tab才查，SQL搜尋及20筆分頁／最大100 |
| /workspace/member-roles | 可授予非ownership角色id／label；workspace.members.manage；成員管理需要時才查 |

既有Owner側欄／深連結入口依workspace.ownership保留；API另依其實際permission授權，Manager仍有既有管理API能力，一般成員僅可讀自己工作區一般設定，不能讀全員信箱或角色管理選項。沒有把Owner顯示名稱當放行條件。

WorkspacePermissions集中固定角色及canManageRole，讀寫共用管理permission、目標授權交集與ownership保留條件。成員role_revision、canChange及原寫入的requestId／重放前授權、版本與交易保留，不新增自訂角色。

舊getWorkspaceSettings RPC及AccountSettings::listing整包組裝、SettingsRepository無上限members查詢已移除，前端adapter／實際呼叫端／後端及瀏覽器測試同步改用分責GET。有效JSON及已登入的舊操作返回404，沒有以相容為由另留全員目錄入口。

## 前端與生命週期

WorkspaceSettings管理一般設定草稿與邀請、成員query保留當頁20筆及搜尋，角色選項獨立。一般tab初始及改名不讀成員／角色；角色變更與邀請只刷新成員分頁，App只重驗最小身分。未儲存名稱／邀請內容仍有離開／切tab確認；角色409不偷換原row revision，明確重載才更新。

工作區變更以元件key卸載／取消及generation清舊資料；每個API檢查X-Workspace-ID與當次Session，失權或切到其他工作區清掉私有名單及邀請連結。未開啟成員tab不因bootstrap附帶名單。手動頁首刷新可重讀目前設定工具，不依賴整包Workspace。

AccountSettings由AccountSettingsFactory組裝當次actor，SettingsRepository／RecordStoreFactory／RequestReplay使用Container穩定注入，ProjectSignals在Factory綁當次actor；未註冊帶租戶singleton或增加巢狀交易。ApiExecution保留User→Workspace鎖、原transaction owner與commit後訊號。

工作區訊號仍重驗所有Project訂閱，但改只cursor讀同Workspace的public_id，不還原Project狀態／歷史全集；原afterCommit派送順序與不帶內容事件不變。此處仍隨Project數增加派送量，不宣稱同步成本為O(1)。

## 八項存取審查

1. 一般設定、當頁成員、可授予角色欄位明確，不以大bootstrap附帶管理資料。
2. 成員預設20／最大100，姓名／user ID穩定排序；前端真實分頁，不切下載的全集。
3. Workspace／disabled範圍、姓名／信箱literal搜尋與分頁在SQL。
4. 設定route用auth/current最小殼；一般設定不讀Workspace卡片／工時／成員全集。
5. 成員及角色只於對應tab需要時讀取，無ACL／描述／歷史內容。
6. 20與100人SQL次數相同；COUNT／深OFFSET／文字搜尋仍有成長成本。角色／改名訊號只投影Project IDs，不讀歷史。沒有聲稱遠端p95或常數成本。
7. 人造106有效成員，雙引擎驗100＋6分頁、未知scope409、Owner／Manager／一般成員、canChange／ownership、版本衝突及另一Workspace隔離；一般設定查詢記錄不讀管理投影／audit／Project ACL。
8. 本卡不取代其他同步、runtime、bundle、工具相容入口退役與正式拓樸量測工作，不宣稱全效能驗收通過。

## 驗證

- 既有設定／邀請回歸6項／108斷言通過；新設定讀取雙引擎2項／100斷言通過，後續補Project訊號只讀ID檢查。
- 完整memory：80 passed／3725 assertions，14 native skips；獨立MySQL：94 passed／4163 assertions。
- 真實Chrome既有設定三尺寸回歸通過：獨立頁、分頁網址／重整／返回、草稿與送出保護、改名衝突、邀請與身份、無權深連結、登出保護。
- 真實Chrome有界查詢：一般設定及改名無目錄／角色請求、22人20＋2、角色／邀請局部刷新、409重載、搜尋、ownership保留、跨Workspace清除、三尺寸及bulkReads=0通過。
- Pint16檔、Vite build及composables通過；主要JS chunk 609.02kB仍有既有警告，由TT-183追蹤。最後設定／雙引擎新查詢／Realtime重跑9項／205斷言通過。
- 僅使用隔離人造資料與獨立MySQL schema；沒有對真實業務資料庫seed／RefreshDatabase／壓測，沒有flush共享Redis。
