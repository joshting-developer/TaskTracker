# TaskTracker API

使用個人 PAT，以標準 HTTP 查詢專案、建立卡片、更新與留言。Workspace → Project → Issue 是資源層級；PAT 綁定一個 Workspace，可見 Project 仍由使用者當下資格決定。

## 開始使用

1. 登入網頁，切換目標 Workspace，從右上帳號選單建立「個人 API Token」。查詢用 read；需要建卡／更新／留言時用 write。
2. 將部署 origin 設成 `BASE_URL`，PAT 透過私有環境值 `TASKTRACKER_TOKEN` 提供。公開 API 前綴是 `/api/public/v1`，不用 Cookie 或 CSRF。
3. 呼叫 `GET /me` 確認身分，再 `GET /projects` 取得可見 Project ID。
4. 從 [完整範例](/api/public/docs/examples) 完成「狀態候選 → 建卡 → 卡號定位 → 最新詳細 → 更新 → 留言 → 驗證」。

```sh
curl --fail-with-body -H "Authorization: Bearer $TASKTRACKER_TOKEN" \
  "$BASE_URL/api/public/v1/me"
curl --fail-with-body -H "Authorization: Bearer $TASKTRACKER_TOKEN" \
  "$BASE_URL/api/public/v1/projects?perPage=20"
```

## 文件與規格

- [認證與權限](/api/public/docs/authentication)：Scope、固定工作區、期限與撤銷。
- [完整操作範例](/api/public/docs/examples)：可下載的 cURL Shell 與 Python，使用人造內容與環境變數。
- [Agent／CLI 指南](/api/public/docs/agents)：最小操作脈絡、精準定位與錯誤續接。
- [HTTP 契約](/api/public/docs/contract)：版本、分頁、錯誤、限流、重試與棄用規則。
- [OpenAPI JSON](/api/public/openapi.json)：唯一正式機器規格；只列已實作操作。

每頁「Markdown 原文」提供工具可讀文字。API／文件公開可閱讀，不表示未授權可取得業務資料，也不讓外部 Agent 自動取得 HTTP 工具或操作授權。

目前有讀取與 Issue 建立／PATCH／留言，沒有公開刪除／封存、MCP 或 Agent 編排。角色名稱不是 API 授權依據；能力與本人有效 permission／scope／資源條件取交集。
