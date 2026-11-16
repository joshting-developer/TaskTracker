# GPT / MCP Plugin功能規劃

最新：本規劃已交付私人第3版, 看板現12張closed、TT-406與TT-408兩張review；完整工具與Host未驗限制見[gpt-plugin-delivery-2026-11-16.md](gpt-plugin-delivery-2026-11-16.md)。下方是建卡當時快照, 不再代表仍未開工

2026-11-16 Josh確認原GPT連線目標完成, TT-350結案。新看板只規劃PAT功能擴充, 本輪未開始實作新工具

看板：[GPT / MCP Plugin](https://task-tracker.josh-ting.com/app/#/projects/5a451d5c-2fff-4110-aa49-4998ec9dbff2/board/5c227e22-7b6e-4300-8f5c-07ca85d551c2)

正式站OpenAPI 0.22.0共41個Bearer業務操作, 與下表逐項比對無遺漏。只計/api/public/v1, 不把Session PAT管理、尚未公開的工時、標籤或工作區管理當成目前Token能力。未來正式規格升版由TT-408補盤點

| 卡片 | 範圍 | operationId |
| --- | --- | --- |
| TT-348／349／350 | 已完成私人連線與8個唯讀工具 | getTokenIdentity, listPublicProjects, listPublicIssues, getPublicIssue, listPublicIssueComments, listPublicBoards, getPublicBoard, listPublicBoardIssues |
| TT-351 | 沿用既有卡：卡片建立、部分更新及留言 | createPublicIssue, updatePublicIssue, createPublicIssueComment |
| TT-399 | GPT Plugin：查詢專案詳細、成員與狀態候選 | getPublicProject, listPublicProjectMembers, listPublicProjectStatuses |
| TT-400 | GPT Plugin：建立、修改看板與差量收錄卡片 | createPublicBoard, patchPublicBoard |
| TT-401 | GPT Plugin：經明確確認刪除卡片 | deletePublicIssue |
| TT-402 | GPT Plugin：外部識別查詢、upsert與批次匯入 | getPublicIssueByExternalId, upsertPublicIssueByExternalId, batchUpsertPublicIssuesByExternalId |
| TT-403 | GPT Plugin：增量任務變更與刪除同步 | listPublicIssueChanges, listPublicIssueDeletions |
| TT-404 | GPT Plugin：讀取紙條、留言與交接紀錄 | listPublicTodos, getPublicTodo, listPublicTodoAssignees, getPublicTodoComments, getPublicTodoHandoffs |
| TT-405 | GPT Plugin：建立、編輯、交辦及完成紙條 | createPublicTodo, patchPublicTodo, completePublicTodo, reopenPublicTodo, returnPublicTodo, commentPublicTodo, forwardPublicTodo |
| TT-406 | GPT Plugin：附件限制、上傳、綁定與下載 | getPublicAttachmentLimits, uploadPublicAttachment, getPublicAttachment, listPublicIssueAttachments, getPublicAttachmentContent, bindPublicAttachment |
| TT-407 | GPT Plugin：查詢本人任務通知 | listMyNotifications |
| TT-408 | GPT Plugin：完整PAT工具驗收與版本覆蓋檢查 | 全部41個操作的完整驗收與規格漂移 |

看板共14張：3張完成、11張待辦。TT-351解除舊帳號blocked, 保留120分鐘估時, 拆出看板功能並回open未指派；其餘新建TT-399～408同樣open未指派, 各有正式估時、操作範圍與驗收條件。已從AI整合/API移出同批明確收錄, 避免兩個主要主題板重複

TT-408承接撤銷／過期與執行時Secret檢查等未重驗項目, 不將原連線結案當成完整安全測試通過。TT-352多人OAuth及TT-388未公開API盤點仍保留原排程, 不擴大到多人或新後端能力

每張寫入卡都要先列對象與變更供確認, 使用最新revision與穩定requestId。刪除只是建立待辦, 沒有刪除任何業務資料。附件須驗平台真實檔案傳遞能力, 不能只回URL冒稱完成。既有工時保留, 本輪未補造工時
