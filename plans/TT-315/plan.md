# TT-315 提供公開API讓第三方與Agent讀取自己的通知

- `GET /api/public/v1/notifications`：只回寄給Token本人的任務通知, 依產生順序遞增, 用 `after`／`nextAfter` 接著讀, `limit` 1–100
- 每次重驗本人現在能不能讀那個專案（issue.read）, 不能讀的跳過, 位置照樣往前
- 只回摘要（id、kind、subject、projectId、issueId、identifier、createdAt）, 不帶正文和Email
- 不做未讀篩選和標記已讀：網頁的任務通知沒有已讀狀態；小待辦用既有 `/todos?scope=mine`
- OpenAPI 0.17.0、HTTP契約、Agent指南、共用task-tracker-usage Skill（CLI新增 `notifications`）一起更新
