# GPT / MCP Plugin功能交付

2026-11-16。依Josh開工與精簡驗證指示, 只做私人Adapter薄轉接, 不重做已驗證的API業務。41個正式PAT操作逐項對應, 24個讀取工具與17個寫入工具

## 私人發布與進度

第3版已部署成功：https://tasktracker-joshting-bridge.joshting-1999.chatgpt.site 。deployment為appgdep_6afa7331b2708191b4b90d67146d17d4, env revision20, source SHA為3f8a750054b096de27c1bd472a02574081242918, 主repository交付至82d86e7已推送。只保留joshting.1999@gmail.com唯一owner, 無群組／外部訪客

原生who_am_i與get_issue(TT-408)已成功。當前對話工具表仍只有原8工具；Site服務憑證的tools/list探查回401, 未繞過身份或假稱線上已逐一呼叫41工具。請在新對話使用既有Plugin確認新工具載入, 不另建或重裝另一個Plugin

已結案TT-351、TT-399～405及TT-407共9張功能轉接卡；TT-406附件Host體驗與TT-408整體Host工具載入／剩餘安全實測保留review。加上原連線3張, 看板讀回12張closed、2張review。本輪專用13049隔離容器與網路已清理, 其他對話及本地開發服務未停

## 驗證與限制

- 77項Adapter測試及build通過；線上0.22.0的41個operationId、method與path一對一核對通過
- 本輪前段獨立13049/MySQL合成HTTP驗專案候選、卡片建立／更新／留言／重播／409／唯讀scope、紙條全生命週期及兩成員轉發／退回、看板差量、合成卡刪除通過。後段依Josh指示只驗Adapter對應與投影, 不重跑核心API
- owner／Token工作區／scope每次驗證；簽章確認綁定操作、內容、目標、本人、requestId及有效期, 伺服器仍重驗即時權限和revision
- Worker沒有console輸出, 只回安全錯誤碼；檔案預覽不輸出base64, 下載使用真實MCP resource
- 附件上限6MiB；Host選檔／呈現、正式PAT撤銷／到期、平台執行時log尚未重新實測, TT-406及TT-408保留待驗收, 不冒稱全部通過
- 未執行與私人Adapter無關的產品前端smoke／視覺回歸, 沒有產品畫面或核心Laravel變更；其他對話工作樹差異未提交
- 正式工時尚未填報, 不用留言數字替代或補造歷史工時

## 操作對照

| 工具 | 方法 | operationId |
| --- | --- | --- |
| who_am_i | GET | getTokenIdentity |
| list_projects | GET | listPublicProjects |
| search_issues | GET | listPublicIssues |
| get_issue | GET | getPublicIssue |
| list_issue_comments | GET | listPublicIssueComments |
| list_boards | GET | listPublicBoards |
| get_board | GET | getPublicBoard |
| list_board_issues | GET | listPublicBoardIssues |
| get_project | GET | getPublicProject |
| list_project_members | GET | listPublicProjectMembers |
| list_project_statuses | GET | listPublicProjectStatuses |
| create_issue | POST | createPublicIssue |
| update_issue | PATCH | updatePublicIssue |
| comment_issue | POST | createPublicIssueComment |
| list_todos | GET | listPublicTodos |
| get_todo | GET | getPublicTodo |
| list_todo_assignees | GET | listPublicTodoAssignees |
| list_todo_comments | GET | getPublicTodoComments |
| list_todo_handoffs | GET | getPublicTodoHandoffs |
| create_todo | POST | createPublicTodo |
| update_todo | PATCH | patchPublicTodo |
| complete_todo | POST | completePublicTodo |
| reopen_todo | POST | reopenPublicTodo |
| return_todo | POST | returnPublicTodo |
| comment_todo | POST | commentPublicTodo |
| forward_todo | POST | forwardPublicTodo |
| create_board | POST | createPublicBoard |
| update_board | PATCH | patchPublicBoard |
| delete_issue | DELETE | deletePublicIssue |
| get_external_issue | GET | getPublicIssueByExternalId |
| upsert_external_issue | PUT | upsertPublicIssueByExternalId |
| batch_upsert_issues | POST | batchUpsertPublicIssuesByExternalId |
| list_issue_changes | GET | listPublicIssueChanges |
| list_issue_deletions | GET | listPublicIssueDeletions |
| list_notifications | GET | listMyNotifications |
| get_attachment_limits | GET | getPublicAttachmentLimits |
| get_attachment | GET | getPublicAttachment |
| list_issue_attachments | GET | listPublicIssueAttachments |
| get_attachment_content | GET | getPublicAttachmentContent |
| bind_attachment | POST | bindPublicAttachment |
| upload_attachment | POST | uploadPublicAttachment |
