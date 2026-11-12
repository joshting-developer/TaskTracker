# 多工作空間與帳號邀請

更新：2026-11-06（Asia/Taipei）

使用者確認：Owner側欄提供工作空間設定；上方工作區區塊改成選擇已加入的工作區；既有帳號也能受邀加入其他工作區。帳號為全域，Workspace membership、身份、專案ACL、待辦及工時仍按工作區隔離。

## API契約

全部使用既有JSON、Session、CSRF與錯誤格式。以下帳號入口屬於`/api/auth/`：

| 操作 | 輸入 | 行為 |
| --- | --- | --- |
| workspaces | `{}` | 只列本人未停用的工作區公開ID、名稱，預載關聯避免N+1 |
| switchWorkspace | `workspaceId` | 重查本人資格，Session切至該工作區，回傳可信context；不是成員回404 |
| invitation | `inviteToken` | 持有token可預覽目標名稱、Email、期限及已使用狀態，不消耗邀請 |
| acceptWorkspaceInvite | `inviteToken` | 必須登入且Email相同，新增一般成員資格並切到目標空間；不建立另一個user |
| login | 原Email／password＋可選`inviteToken` | 密碼正確後可在同交易接受邀請並登入目標空間 |

原`/api/rpc/inviteMember`保留`workspace.invite`檢查，移除「Email必須全新」限制；若該帳號已是同工作區成員，回409。邀請仍是7日單次連結，不會因管理者輸入Email而自動加入，不寄真實郵件。新帳號仍走原註冊加入流程。

## 邊界與重試

- 所有資格由伺服器登入帳號及目前membership重查，不比對前端角色放行。
- 接受邀請只建立目標Workspace的`user`資格，不授予專案ACL，不改動原工作區角色、個人資料、密碼或既有內容。
- 不同Email回403；過期／未知token回400，停用資格不可透過邀請重新啟用。
- 重試已使用的token，僅在同Email且已有有效資格時成功；不重複插入或把既有Manager／Owner降成一般成員。未加入者不能重用已消耗token。
- 接受與消耗token在既有DB交易內完成；同Session的auth／rpc／realtime與CSRF請求使用Laravel Session blocking，避免舊Session存回覆蓋選擇。
- 前端以`X-Workspace-ID`表示畫面所屬scope。後端在業務授權及重試快取之前比對Session，不符回409 `WORKSPACE_CHANGED`，避免其他分頁切換後舊表單寫進新工作區。此header不是授權來源，未帶時仍使用Session與原資源授權；`getWorkspace`用於重新取得可信scope。
- 普通登入選本人第一個有效資格，這輪不新增「上次登入工作區」持久化偏好或建立第二工作區功能。

## 介面方向

2026-11-06：工作空間設定由彈窗改為獨立頁面。一般設定網址為`#/workspace/settings`，成員與權限為`#/workspace/settings/members`，分頁切換會更新網址；可重整、分享與瀏覽器上一頁／下一頁。從專案進入後離開專案上下文，側欄切換為工作空間導覽；返回專案可用瀏覽器上一頁或專案列表。

設定頁沿用一般設定／成員與權限及原API；改名、角色確認、邀請連結、版本衝突保留草稿與重試均保留。未保存內容在離開、切分頁、切空間、登出及瀏覽器離開時受保護，送出中不能離開；取消瀏覽器返回會恢復目前設定網址與草稿。Owner入口與直接網址都依有效`workspace.ownership`檢查；無權限或被撤銷時回首頁並移除頁面，不擴張後端Manager權限。未來設定分區可沿用路由與頁面結構，本輪不新增其他設定項目。

Owner設定入口以`workspace.ownership` permission呈現，不以role字串判斷；這輪移動入口，不撤銷Manager原有後端管理／邀請權限。上方切換器列出本人已加入的空間，邀請連結可在登入中直接接受，也可讓既有帳號登入接受或新帳號註冊。

切換前確認未送出草稿；切換成功回首頁並清除舊專案、待辦、通知、看板與表單。跨分頁重讀發現scope改變亦清除；舊回應由前端generation捨棄，舊表單請求由後端scope檢查拒寫。

## 階段驗證

後端階段已完成：隔離SQLite完整53 tests／1073 assertions通過，含既有帳號登入接受、Email錯誤、過期、單次重試、身份不降級、停用拒絕、成員目錄範圍、原私有專案不可見與舊scope拒寫。前端階段也已完成：編譯版多工作區測試、帳號設定與完整browser-check通過。包含切換網路失敗／重試、草稿取消／確認、其他分頁409拒寫／重讀、Email不符換帳號保留邀請、新帳號註冊與未登入既有帳號登入接受；切換器及邀請面板1440／768／390尺寸已量測並檢視。後端新映像已套用，現有volume及資料保留。

## 2026-11-12 慢請求Session序列保護

TT-224已重現原10秒TTL到期後舊Session覆寫新空間。現用MySQL由GuardedStartSession持有連線級鎖至保存，等待上限10秒、失鎖拒絕提交；SQLite僅快測保留原框架鎖。成本、原生四worker證據與需整批更新HTTP worker的發布限制見[鎖生命週期](session-lock-lifecycle-2026-11-12.md)。
