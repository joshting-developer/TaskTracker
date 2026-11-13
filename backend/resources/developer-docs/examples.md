# 完整操作範例

以下範例會實際建立一張卡片、更新為 closed 並留言。只在你已獲授權可新增示範內容的 Project 執行；範例不呼叫刪除；需要清理時由有 issue.delete 的人另行決定。假資料、環境變數不代表授權或真實帳號。

先提供 `BASE_URL`（部署 origin）、`TASKTRACKER_TOKEN`（私有環境 PAT）與 `PROJECT_ID`；可先 `GET /api/public/v1/projects` 找可見專案。Token 需 write 且本人有該 Project 寫入資格。原文不可放進 shell 歷史、對話或 Git。

## cURL Shell

需要 curl 與 Python 3（只處理 JSON／UUID，不呼叫私有服務）。[下載原文](/api/public/docs/examples.sh)，先閱讀，再明確 `CONFIRM_WRITE=1 sh workflow.sh`。沿 requestId 重送核對是否重複；獨立操作使用不同 key。

```sh
{{CURL_EXAMPLE}}
```

## Python 標準 HTTP

沒有 CookieJar、TaskTracker SDK、原始碼或特殊 RPC。使用公開規格中的路徑與欄位；[下載原文](/api/public/docs/examples.py)，先閱讀，再 `CONFIRM_WRITE=1 python3 workflow.py`。

```python
{{PYTHON_EXAMPLE}}
```

## 小待辦流程

兩支範例最後都會建立一筆**自己的**待辦, 用同一個requestId重送確認沒有建第二筆, 再從「交辦給我的未完成待辦」裡找到它並完成。交辦給別人時多帶 `assigneeId`（從 `GET /todo-assignees` 取得）。

```sh
curl --fail-with-body -X POST -H "Authorization: Bearer $TASKTRACKER_TOKEN" -H 'Content-Type: application/json' \
  --data '{"content":"請複查TT-311\n\n完成條件：看過OpenAPI","assigneeId":"<User ID>","requestId":"<新的UUID>"}' \
  "$BASE_URL/api/public/v1/todos"
curl --fail-with-body -X POST -H "Authorization: Bearer $TASKTRACKER_TOKEN" -H 'Content-Type: application/json' \
  --data '{"expectedRevision":1,"requestId":"<新的UUID>"}' \
  "$BASE_URL/api/public/v1/todos/<待辦ID>/complete"
```

## 成功結果與衝突

兩個範例都完成：身分／Project → 狀態候選 → 建卡 → identifier 精準列表 → 最新詳細 → PATCH → 留言 → GET 驗證。同一請求重送不重複卡片或留言；最終 revision 至少3。只輸出識別／驗證及非秘密結果，不輸出 PAT。分頁、409／429與timeout策略見 [HTTP 契約](/api/public/docs/contract) 與 [Agent 指南](/api/public/docs/agents)。

實務中請保存每次 requestId 與有效 payload 供重試。範例沒有自動「衝突後覆蓋」：如果中途有人修改，會停止；先 GET 最新並確認意圖，再決定下一次操作。

Python範例另外驗證增量摘要cursor接續與inclusive水位；處理成功才保存接續點，不將全集放在記憶體。


## 圖片上傳與建卡

Python下載範例可選擇帶本機圖片：`CONFIRM_WRITE=1 IMAGE_FILE=/本機/測試.png python3 workflow.py`。除了既有BASE_URL、TASKTRACKER_TOKEN與PROJECT_ID, 不需Cookie或SDK；它先取得限制, 以multipart上傳並重送同一requestId, 再建立包含參照的Issue, 查metadata與讀回圖片核對SHA256。沒有IMAGE_FILE時維持原本純文字流程

用cURL上傳時不要自行設定multipart Content-Type, 讓curl產生boundary：

```sh
curl --fail-with-body -H "Authorization: Bearer $TASKTRACKER_TOKEN" \
  -F "requestId=<本次保留的UUID>" -F "file=@/本機/測試.png;type=image/png" \
  "$BASE_URL/api/public/v1/projects/$PROJECT_ID/attachments"
```

保留回傳的reference, 將`![說明](attachment:<UUID>)`放進建卡description, 即可和新卡同交易綁定。明確綁定現有卡時使用OpenAPI的bindPublicAttachment, 同時帶附件與卡片的最新revision；409先讀回再決定, 不盲目重送新版本。上傳大小／格式／配額與24小時到期見Agent指南及limits, 不提供遠端URL匯入
