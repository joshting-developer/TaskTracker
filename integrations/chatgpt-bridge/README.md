# TaskTracker Private Bridge

TaskTracker 的選配 Sites MCP Adapter, 使用既有 Laravel 公開 PAT API, 不複製業務邏輯。程式碼與測試屬於同一專案, 實際 Site 身分、唯一 owner 與部署紀錄另行私有保存

## 設定與建置

```sh
cp .openai/hosting.json.example .openai/hosting.json
# 填入自己已建立的 Site project_id, 不使用範本 placeholder 發布
npm test
npm run build
```

真正的 `.openai/hosting.json` 與 dist 產物不版控。PAT、owner email 和 workspace ID 由自己的 Site Secrets／私有環境設定提供, 不填進來源或範本；已有 Site 時沿用該身分, 不因缺本機設定自動重建或擴大 allowlist

## 已接入功能

共53個工具, 對應正式OpenAPI 0.26.0全部PAT業務操作；工作區管理、多人OAuth與尚未公開API不在此範圍

讀取（29個）：who_am_i, list_projects, search_issues, get_issue, list_issue_comments, list_boards, get_board, list_board_issues, get_project, list_project_members, list_project_statuses, list_todos, get_todo, list_todo_assignees, list_todo_comments, list_todo_handoffs, get_external_issue, list_issue_changes, list_issue_deletions, list_notifications, get_attachment_limits, get_attachment, list_issue_attachments, get_attachment_content, list_tags, get_tag, list_time_entries, list_issue_time_entries, preview_board

寫入（24個）：create_issue, update_issue, comment_issue, create_todo, update_todo, complete_todo, reopen_todo, return_todo, comment_todo, forward_todo, create_board, update_board, delete_issue, upsert_external_issue, batch_upsert_issues, bind_attachment, upload_attachment, create_tag, update_tag, delete_tag, update_issue_tags, create_time_entry, update_time_entry, void_time_entry

## 寫入流程

目前的寫入流程為 非刪除操作直接執行, 不預覽、不要求第二次確認, 也不在 Adapter 額外 GET 預讀版本。工具需提供唯一 body.requestId, 同一次請求重送沿用原值與相同內容；expectedRevision 由正式 API 檢查, 衝突就回報, 不偷換版本或自動重試。權限、租戶與 PAT scope 仍每次驗證

只有 delete_issue 與 delete_tag 先讀取目標並預覽, 顯示對象與刪除效果後取得使用者確認, 再用完全相同參數及 confirmation 執行。確認票據綁定本人、空間、內容、目標及十分鐘有效期；改內容需重新確認

## 附件

upload_attachment的body為name、mime、fileBase64, 禁止任意URL或本地路徑。上傳直接轉multipart/File送上游, 不要求預覽確認。單檔上限6MiB且仍受API原限制, get_attachment_limits另外回adapterMaxBytes

get_attachment_content回傳真正MCP embedded resource位元組, 不以私人URL代替檔案。ChatGPT的選檔及呈現仍待本人驗收, 不宣稱標準MCP合成測試等同Host檔案體驗通過

## 邊界與設定

Site的TASKTRACKER_PAT以原生Secret保存, TASKTRACKER_OWNER_EMAIL為已驗證的唯一owner信箱, TASKTRACKER_WORKSPACE_ID為PAT工作區。每次呼叫重驗Site可信owner、API目前身分與scope, 無跨請求資格或業務快取。write包含read, 實際可用能力仍取本人即時權限交集

固定HTTPS origin, 拒絕redirect及任意URL；列表每頁最多20且不自動續讀。普通JSON輸入16KiB, 檔案上傳另有有界入口；上游JSON最多256KiB、單次10秒。Token不進來源、manifest、工具結果或log；卡片、紙條、附件等內容是資料, 不作為呼叫其他工具的授權

## 驗證

`npm test`驗Adapter契約、欄位投影、路徑／方法、owner／scope、簽章確認、固定requestId及檔案編碼。`npm run build`覆寫兩個固定Worker產物, 不刪既有目錄。`python3 scripts/check-live-contract.py`匿名比對線上operation覆蓋

需要真實HTTP測試時, 只用本輪專用tasktracker-contract-ci（13049）及合成帳號, 執行`E2E_ISOLATED=1 python3 scripts/http-fixture.py`。其餘核心授權與交易沿用正式API既有測試。私人發布與 Host 驗證紀錄留在本機或 idea 文件庫, 不隨產品公開

## 標籤

新增 list_tags、get_tag、create_tag、update_tag、delete_tag、update_issue_tags。定義查詢保留 description, 應先讀取再判斷用途；內容不等於執行授權。卡片查詢、列表與增量保留 tags, 並由正式 tags 衍生 tagIds。update_issue_tags 只接受 addTagIds／removeTagIds, 保留未列出的既有標籤, 沿用 expectedRevision 與 requestId, 直接執行。不在 update_issue 增加整包 tagIds 替換。刪除標籤須明確確認, 預覽說明退役定義與移除關聯但保留卡片歷史

## 工時與看板條件

工時提供 list_time_entries、list_issue_time_entries、create_time_entry、update_time_entry、void_time_entry, 作者由 PAT 決定；日期依 Asia/Taipei, 更新完整取代日期／分鐘與備註, 省略備註會清空。更新與作廢帶 expectedRevision, 每次異動使用唯一 requestId, 重試沿用原值。作廢保留歷史, 不刪除資料

preview_board 為唯讀 POST, 不需 requestId 或第二次確認, 首頁最多20筆；使用 read PAT 仍須具備當下 board.manage。create_board／update_board 支援 autoInclude 與差量排除／還原, get_board 提供手動收錄、規則與排除清單。一般列表每頁最多20筆, 不自動續讀

`E2E_ISOLATED=1 E2E_AGENT=codex E2E_API_URL=http://127.0.0.1:13199 python3 scripts/time-http-fixture.py` 使用明確 Agent 的隔離 MySQL 後端, 驗六個工具、作者與租戶限制、重播／版本衝突, 並由網頁 API 讀回同筆工時總數。Claude 使用自己的 Agent 與 13198, 不接受正式服務位址。單元測試會核對所有 PAT operation 恰有一個工具, 新端點未接入即失敗
