# 公開寫入API（TT-169）

2026-11-12。正式OpenAPI0.4.0：11 paths、15 operations；新增Issue建立、PATCH與留言POST。公開只讀仍沿TT-168，共用同一PAT／permission／Workspace邊界，無刪除／封存／MCP。

## 入口與輸入

公開前綴`/api/public/v1/projects/{project}/issues`：POST建立、PATCH `/{issue}`部分更新、POST `/{issue}/comments`新增留言。全部要求Bearer write與本人當下issue.write、UUID requestId及最多50000 bytes JSON物件。更新與留言另需expectedRevision，從單卡GET的revision取得，嚴格JSON整數而非字串。未提供／驗證失敗400，不改成422。

路徑控制Project／Issue，payload中的id、projectId、actor、author或Workspace等未知欄位忽略，不授予資格。驗證只取allowlist；PATCH省略欄位保留原值，清除使用空字串或空陣列，不接受null。關聯與父子／Epic規則完全沿現有IssueService：一層子任務、子卡跟父卡Epic、同Project、雙向關聯、最多200筆、無前置任務或結案阻擋。有效成員／狀態與markdown上限仍由既有業務規則驗證。

成功HTTP200，data僅`id/projectId/identifier/revision`，不附整張卡片留言／歷史／描述。最新卡片與留言另按需GET；寫入回應不是新的完整編輯基準。

## 業務、交易與冪等

- `BearerTokenSession.writeOnce`：交易及User→Workspace鎖後重驗Token／Scope／Workspace／Project，再驗路徑Issue所屬Project，**之後才讀receipt**；不讓舊結果繞過撤權。
- 現有WorkspaceService新增明確的`executePublicIssue`，僅允許createIssue／updateIssue／addComment，沿同一IssueService、集中授權、關聯、通知與afterCommit訊號。不呼叫HTTP Session RPC，不複製業務寫入。
- 外層RequestReplay保存有界結果，操作名`public.v1.createIssue/updateIssue/addComment`，和Session舊操作名分開；不改寫舊receipt或舊Session回應。相同user／Workspace／requestId及相同有效內容回原結果，JSON物件key順序不重要；異內容或跨接口誤用同key回409 REQUEST_CONFLICT。
- 首次真正成功寫入才追加Token來源、發業務通知／訊號；receipt重播不再次執行。Token ID／名稱／能力只保存metadata，不保存原文。若業務／audit／receipt失敗，同交易rollback。
- 首次操作失敗不保存成功receipt，可修正後再送；若是timeout不知道是否成功，須先用原requestId與原內容核對，不自動換UUID。
- 過期expectedRevision回409 REVISION_CONFLICT；相同請求重播則回**原**revision，即使卡片後來有其他修改也不偷換成最新結果。此時要GET取得當前狀態。

## 使用範例與限制

正式欄位、Schema及假資料範例在[唯一OpenAPI](../backend/resources/openapi.json)，匿名文字契約來源[public-api-contract.md](../backend/resources/public-api-contract.md)。`tools/public_write_example.py`只用標準HTTP與環境PAT，沒有Cookie／原始碼服務匯入；明確`CONFIRM_WRITE=1`才會在指定Project建一張範例卡、驗同key重播、關閉並留言。不要對不允許新增範例卡的正式Project執行。

```sh
# TASKTRACKER_TOKEN預先透過私有方式提供，不貼進shell歷史或Git。
BASE_URL=https://your-tasktracker.example PROJECT_ID=your-project \
CONFIRM_WRITE=1 python3 tools/public_write_example.py
```

不輸出PAT、描述／留言或使用者資訊，只回驗證結果與revision。測試使用隔離tasktracker_test；該範例是可重現流程，不等同TT-254尚待完成的「僅公開文件、無原始碼」獨立驗收。完整開發者站與CI續TT-252／253。

## 資料存取及容量審查

公開寫入不讀整個Workspace；路徑資源先限定Project，沿既有資料存取與作者／版本規則。共用舊IssueService仍會讀目前卡片的必要舊資料及其歷史以維持既有歷史／通知／關聯寫入，這不是本卡宣稱已完成舊資料模型全面優化的理由。新增receipt與HTTP結果固定四欄位，測試回應小於400 bytes，不因評論／歷史數量增加而回傳全集。

PATCH不把列表摘要當完整儲存來源，省略關聯集合不清空。跨Project／Workspace或已撤權先拒絕；讀receipt前重驗，避免原資料洩漏。GET與POST共用公開v1每IP120／60秒，沒有新增背景輪詢或權限cache。回應、通知與來源保持同交易／afterCommit邊界，未引入第二套生效規則。

未承諾自動receipt TTL／清除，也不改舊資料保留政策。此為功能／安全／容量形狀驗收，不是正式HTTP p95或負載證書。

## 本輪驗證

完整memory130通過／51原生類略過（5735 assertions）；原生MySQL寫入／撤銷競態8通過（285 assertions），包含原結果重播、關聯與rollback；受影響正式契約17通過976 assertions。獨立無Cookie HTTP流程PASS（建卡一次→關閉→留言一次，最終revision3），Token測後撤銷。官方OpenAPI結構與Pint通過，既有PAT Chrome流程相容。

本機backend／queue／websocket載入新版，JSON與Markdown符合正式來源，無migration／seed／reset現用主庫。這是本機功能／安全驗證，未推送遠端或完成正式負載／第三方全流程驗收。
