# 看板列表與收錄設計

更新：2026-10-20（Asia/Taipei）

## 使用者確認

提供看板列表, 有固定 Default 看板包含全部範圍內卡片；可以建立自訂看板, 明確選擇 Epic 或一般卡片, 選 Epic 時收錄它底下的卡片

使用者已回覆確認 Default 包含「目前專案的所有卡片」。看板綁定 Project, 不新增跨專案或跨 Workspace 看板；沿用目前專案的狀態欄。本文件其餘操作細節是本輪實作選擇, 非使用者逐項指定

## 收錄語意

- 看板引用既有 Issue, 不複製任務、狀態、留言、估時或工時；同卡在多個看板仍使用同一 ID 與 revision
- 自訂看板保存使用者明確選擇的 Issue ID, 可包含 Epic、Task、Bug。選 Epic 時動態展開目前 epicId 指向它的執行卡, 不保存當下的一次性快照
- 後來掛到已收錄 Epic 的卡片自動出現, 移出後自動離開；另被明確選取的卡片則仍保留。Epic 與單卡重複收錄時只顯示一次
- 一般父 Task／Bug 的子任務各是獨立卡片, 單選一般卡片不隱含加入其他卡片；選 Epic 已涵蓋隸屬其 Epic 的父項與子項。一般關聯連結也不擴張收錄
- 解除收錄只更改看板設定, 不刪卡、不解除卡片本身的 Epic／父子／關聯。Default 是系統入口, 不提供改名、改收錄或刪除
- Epic 沿用泳道／容器呈現, 執行卡放在狀態欄；收錄與歸類分開, 看板內變更卡片狀態或 Epic 將影響其他看板
- 搜尋／即時篩選再與看板收錄範圍取交集, 不因清除篩選擴張至未收錄卡片

## 資料與操作契約

自訂看板包含固定 ID、所屬範圍、名稱、明確選取 ID、revision、建立者與時間。Default 可由伺服器產生, 避免新舊專案漏建或重建重複資料。自訂看板提供建立、編輯收錄及移除入口；移除看板保留 Issue

設定變更透過 Laravel API, 交易內檢查有效 permission、目標範圍、ID 與 revision, requestId 防重複。只有前端隱藏選項不構成授權。跨 Workspace 一律拒絕；最終 Project ACL 與角色矩陣目前尚未完成, 本輪不可藉看板授予新的卡片讀寫權

這是明確收錄卡片的看板, 不恢復已移除的命名篩選／視圖功能, 也不實作首頁個人小清單。看板獨立網址需保留卡片深連結、重整、返回與未儲存表單保護

## 驗證目標

Default 全部範圍、空看板、Epic 動態收錄與未來新增卡片、單卡重複去重、移出 Epic 的明確收錄例外、一般子任務不隱含加入、取消收錄不刪卡、跨空間拒絕、版本衝突保留草稿、防重複提交、桌面／手機及既有拖曳操作


API：getWorkspace.boards 包含每個可讀 Project 的虛擬 Default（id=default、revision=0、isDefault=true）與未封存自訂看板。createBoard／updateBoard 使用 name、projectId、selectedIssueIds；更新及 archiveBoard 帶 id、expectedRevision、requestId。明確收錄上限 1000 項, Epic 動態子卡不計入上限。不需要新 migration, 延用 Workspace namespace 下 boards payload。Default 不寫入資料庫

原型 board.manage 映射目前可編輯任務的成員權限組合, 目前專案內共用, 具備該權限的成員可一起維護看板；不以 createdBy 作管理捷徑。後端以 permission authorize, 不用角色名放行。完整 Project ACL 仍待落地, 不能把本輪的共享原型視為最終權限矩陣

自訂看板內新增 Issue 可帶 boardId：同交易檢查看板、額外 board.manage 權限, 若已有 Epic 覆蓋則不展開快照, 否則明確收錄新卡並增加看板 revision。失敗會連同卡片寫入回滾, 重試沿用 createIssue 的 requestId。不修改任務歸屬以迎合看板
