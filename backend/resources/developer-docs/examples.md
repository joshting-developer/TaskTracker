# 完整操作範例

以下範例會實際建立一張卡片、更新為 closed 並留言。只在你已獲授權可新增示範內容的 Project 執行；沒有刪除清理接口。假資料、環境變數不代表授權或真實帳號。

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

## 成功結果與衝突

兩個範例都完成：身分／Project → 狀態候選 → 建卡 → identifier 精準列表 → 最新詳細 → PATCH → 留言 → GET 驗證。同一請求重送不重複卡片或留言；最終 revision 至少3。只輸出識別／驗證及非秘密結果，不輸出 PAT。分頁、409／429與timeout策略見 [HTTP 契約](/api/public/docs/contract) 與 [Agent 指南](/api/public/docs/agents)。

實務中請保存每次 requestId 與有效 payload 供重試。範例沒有自動「衝突後覆蓋」：如果中途有人修改，會停止；先 GET 最新並確認意圖，再決定下一次操作。
