# 只憑公開文件的獨立客戶端驗收（TT-254）

2026-11-12。本次驗的是工具中立HTTP客戶端，不代表任何聊天工具已自動具備連線能力，也不需要MCP或特定模型插件。

## 邊界與重跑

`tools/clients/public_contract_client.py`只有Python標準HTTP／JSON／UUID，不匯入TaskTracker程式、helper、DOM或DB。由fixture將單一客戶端copy到空暫存目錄，以`python -I`執行；輸入只有Base URL、合法PAT、不透明測試ID及測試文字。業務路徑／method依匿名OpenAPI的operationId解析，先取得公開認證／Agent／contract Markdown，不人工補私有RPC。

fixture控制器為`tools/public_client_acceptance.py`，只允許固定隔離13049；它的Session只做前置人造帳號／Project／Token建立，從未交給客戶端。測試CLI`backend/tools/public-client-fixture.php`只允許testing＋TASKTRACKER_MYSQL_TEST＋MySQL tasktracker_test帳號及schema，且只能改指定contract-client人造帳號的同Workspace Project；依序控制權限／停用／到期，沒有產品HTTP測試路由。

```sh
sh tools/api-contract-ci.sh
```

同TT-253環境需求，macOS可指定已安裝Chrome。runner建立全新專用MySQL tmpfs，測完清理專用環境，不使用主庫／現用帳號／Redis／Agent憑證。PAT僅走stdin記憶體及Bearer header，不寫文件、Git、命令列參數、卡片或驗證輸出；輸出只記phase與PASS。

## 實際結果

| 情境 | 結果 |
| --- | --- |
| 一般Workspace成員＋Project Maintainer，Token write | 只列可見Project、狀態含open／closed；建卡、依卡號精準找卡、讀最新revision、更新內容／closed、留言及回讀成功 |
| 假設成功回應遺失，原requestId／原payload重送 | 建卡僅一張、留言僅一筆，回傳相同結果，最後revision3 |
| 同requestId改內容 | 409 REQUEST_CONFLICT |
| 更新後提交舊版本草稿 | 409 REVISION_CONFLICT；GET讀回更新版，但原草稿expectedRevision維持，不自動覆蓋 |
| read PAT寫入 | 403 FORBIDDEN |
| 同Workspace不可見Project／另一Workspace Project | 404 NOT_FOUND，未取得內容 |
| 保持同write PAT，把Project資格降成Reader | 仍可讀；重播原成功建卡請求也403，receipt不能繞過目前授權 |
| 移除Project資格，再恢復 | 同PAT 404→200 |
| 停用Workspace成員，再恢復 | 同PAT 401→200 |
| 同PAT到期前／後 | 200→401 |
| 同PAT撤銷前／後 | 200→401 |
| 作者與Session邊界 | 留言作者是Token帳號；全部Bearer回應無Set-Cookie |

本地完整CI：memory22通過／13原生類略過（2286 assertions），MySQL14通過（508 assertions），上述客戶端10階段、官方Schema與負向漂移、公開py／sh範例、文件5頁×3尺寸／鍵盤、真HTTP限流皆通過。PHP fixture Pint通過。環境為獨立本地MySQL8.4／PHP8.5，非正式部署／真人可用性研究或效能p95。

## 文件缺口及限制

公開contract Markdown的舊規格版號落後，已改以唯一OpenAPI的info.version為準，避免重複維護另一個版號。本次未發現必須偷讀原始碼才能補齊的路徑／欄位。由專案提供的source-free通用客戶端運行證據，不能冒稱另一個真人或外部模型已完成獨立評測。即時失效指資格變更後下一次請求重驗，不承諾取消先前已在處理中的回應。正式API仍未提供刪除／封存／MCP；後續能力按卡逐項開放。
