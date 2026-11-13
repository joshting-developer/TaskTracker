# 認證與權限

## 建立與保管 PAT

先在網頁切換目標 Workspace，右上帳號選單 → 個人 API Token。原文只在成功建立時顯示一次；伺服器保存雜湊，關閉後無法取回。遺失時撤銷並另建，不嘗試從清單取得原文。

- `read`：讀取；`write` 包含 read，允許進入寫入授權檢查，並不自動取得專案寫入權。
- 一個 PAT 固定一個 Workspace；多 Workspace 需各自建立。Session 或 query 中的工作區不能改寫 PAT 綁定。
- 預設一個月、最長兩年；不提供永久或延期。指定到期日在 Asia/Taipei 該日 00:00 失效，到期後新建。
- 過期只在管理清單顯示，不主動通知；撤銷後後續請求回 401。已回傳資料無法收回，先取得寫鎖的操作可能先完成。
- 原文只放私有環境／憑證管理工具，不貼進對話、卡片、Commit、URL、Prompt、可讀 log 或原始碼。本文沒有 PAT 輸入框，不保存 Token。

## Bearer 與有效權限

```http
Authorization: Bearer <your-private-PAT>
Accept: application/json
```

有效操作 = Token read／write ∩ 使用者目前有效 permission ∩ Token Workspace／目標 Project ∩ 資源／作者條件。讀者即使有 write PAT 仍不能修改；被移除 Project、Workspace 停用、Token 到期／撤銷後，不能以舊 receipt 取回原結果。

公開 API 不接受 Session 替代、不需要 Cookie 或 CSRF。個人 Token 管理 API 則是 Session／CSRF 入口，PAT 不能藉此替自己增發憑證。請勿把 `/api/rpc/*` 或 SPA 的內部 API 當公開介面。

工作區 Owner／Manager 可因有效 permission 操作所有專案；一般成員需各 Project 授權。這是權限映射，第三方工具應依 API 實際 capabilities／拒絕結果，不自行按角色名稱放行。

## 小待辦的權限

小待辦是工作空間層級, 不屬於任何Project。讀待辦要read, 建立、交辦、修改、完成、重開、退回要write。

- 只看得到自己是交辦人或承辦人的待辦, 別人的私人待辦一律回404, 不會透露它存不存在
- 修改內容只有交辦人可以；完成、重開、退回只有目前承辦人可以；交辦給自己的待辦不需要退回
- 交辦對象要用 `GET /todo-assignees` 取得, 只回同工作空間有效成員的ID和名稱, 不回Email

## 錯誤處理

| HTTP | 處理 |
| --- | --- |
| 401 | 檢查 Token、到期／撤銷及 Workspace 資格；停止自動重試，不回退 Owner |
| 403 | 檢查 Scope／有效 Project 寫入權或來源；換 requestId 不會解決 |
| 404 | 資源不存在、跨 Project／Workspace 或不可見，不猜測資料存在性 |
| 409 | 看 error.code；REVISION_CONFLICT 需 GET 最新版本，REQUEST_CONFLICT 需核對原請求 |
| 429 | 至少等待 Retry-After 秒，沿相同請求內容／requestId 退避重試 |

一般驗證為 400；419 只屬 Session CSRF。公開 API 的 rate limit 為所有 v1 操作共用每 IP 120／60 秒。JSON 500 不含 SQL／stack，暫時錯誤也要先核對是否已寫入，不能盲目重複建卡。

## 輪替

建立新 PAT → 更新自己的工具憑證 → 確認新 PAT 可呼叫 → 從原工作區清單撤銷舊 PAT。不要以把原文存進文件、Token 名稱或卡片作為備份方式。本版沒有輪替自動化、無限期 Token 或其他人的 Token 管理介面。
