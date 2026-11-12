# 卡片刪除語意

TT-250／2026-11-13。使用者授權依Claude建議決定；本文為現行契約。

## 決策

| 問題 | 決定 | 理由 |
| --- | --- | --- |
| 刪除或封存 | 只提供**永久刪除**，不做封存、不能還原 | closed已是結案；封存需改所有列表／計數／看板／搜尋查詢，前期非必要 |
| 權限 | Project permission `issue.delete`：負責人／專案管理者，及Workspace擁有者／管理者（`issue.deleteAny`）；開發者／讀者不可 | 刪除不可逆，比編輯更嚴；以permission判斷不看角色名稱 |
| 有工時 | 任何工時（含已作廢）→ 409 CONFLICT，請改closed | 工時是投入與報表依據，不隨卡片消失 |
| Epic所屬卡／子任務 | 仍有 → 409 CONFLICT，先移出 | 避免孤兒卡或外鍵連鎖 |
| 留言、卡片歷史、外部識別、通知預覽 | 一併刪除 | 屬於卡片本身 |
| 雙向關聯 | 另一端移除關聯、revision+1並留異動紀錄 | 讓其他人的草稿以409得知變動 |
| 看板收錄 | 從收錄移除、看板revision+1 | 同上 |
| 由待辦轉換的卡 | 待辦保留，只解除卡片連結 | 待辦是個人資料 |
| 刪除痕跡 | Project稽核串流`issueDeletions`留下墓碑（公開ID、識別碼、標題、操作者、時間）；另有TT-260 HTTP操作紀錄 | 卡片歷史已刪，仍需知道誰刪了什麼 |
| 版本與重送 | 需`expectedRevision`；同requestId重送回原結果（卡片已刪仍回同結果），異內容409 | 沿用既有寫入語意 |
| 授權時機 | 以Project層級`issue.delete`在交易內授權，再讀receipt；Service再核對卡片屬於該Project | 卡片刪除後重播仍能「先授權後重播」 |
| 即時通知 | 沿用寫入後的Project變更訊號；看板版本因卡數減少而改變 | 不新增事件型別 |
| 公開API | `DELETE /api/public/v1/projects/{project}/issues/{issue}`（write Scope＋本人`issue.delete`），不另設刪除Scope；同步端另讀`GET …/issue-deletions`墓碑 | 公開契約先前承諾刪除前提供tombstone，不讓卡片無聲消失 |

工時分析／報表：因有工時的卡不可刪，既有統計不受影響。修改歷史只保留在墓碑與操作紀錄，不再能查卡片本身的歷史。

## 介面

- Session：`POST /api/rpc/deleteIssue`，`{projectId, id, expectedRevision, requestId}`，回`{id, projectId, identifier, revision}`（revision為刪除前＋1）。
- 任務完整頁：具`issue.delete`才顯示「刪除卡片」（次要按鈕、紅字），瀏覽器確認對話框提示無法復原；成功回列表並顯示「任務已刪除」，409顯示原因（例如已有工時）。舊App.vue本機路徑不顯示此按鈕。
- OpenAPI 0.10.0：deletePublicIssue、listPublicIssueDeletions；公開契約與開發者文件同步。

## 驗證

SQLite與獨立MySQL共用`IssueDeletionCases`（連帶清除、關聯端／看板revision、待辦解除連結、墓碑不含描述、版本衝突、重播、工時／Epic／子任務阻擋、公開DELETE與墓碑分頁、跨Project 404）；`ProjectAclTest`驗開發者403、管理者可刪、PAT同樣受permission限制。前端`tools/issue-delete-check.js`驗確認／取消、409訊息、成功返回列表與三尺寸。

視覺基準：07-detail三尺寸因新增按鈕而與核准圖不同（桌機只有按鈕；手機工具列換行），需人工核准後才能更新基準，未核准前TT-250不結案。
