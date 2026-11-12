# 外部識別批次匯入（TT-172）

2026-11-12。正式OpenAPI 0.7.0：14 paths、19 operations。新增`batchUpsertPublicIssuesByExternalId`（`POST /api/public/v1/projects/{project}/issues/external-batch`）。使用規則以[公開契約](../backend/resources/public-api-contract.md)的「批次匯入與逐項結果」為準，本文記錄設計與驗證。外部識別本身見[TT-171](public-external-ids.md)。

## 決策（使用者授權先採Claude建議）

- **單項失敗不連坐**：每項在同一交易中各自用savepoint。失敗項目的卡片、綁定、通知、afterCommit訊號一起回滾，其他項照常提交。
- **整批一個receipt**：以requestId保存整批的逐項有界結果，回應遺失時重送同requestId就能判定結果。
- **分階段匯入**：epicRef／parentRef引用外部識別，可指向同批前面的項目或既有綁定。
- **createOnly**：支援「中斷後補跑」，已綁定的項目回exists、不更新、不重複。
- **上限50項**：整個請求仍受50000 bytes限制；來源稽核每批一筆。

## 實作

- PublicIssueBatchRequest：結構驗證（項目上限、同批重複、Ref與ID互斥），卡片欄位與單筆共用`PublicIssueWriteRequest::issueFields`。
- IssueExternalReferences::batch：整批只授權一次Project，逐項呼叫共用的apply（單筆upsert也改用它），卡片寫入仍走WorkspaceService.executePublicIssue。
- IssueExternalRefRepository::summary：提供createOnly的既有卡片摘要。
- BearerTokenSession.writeOnce：稽核resource_id允許結果沒有id（批次）。

## 驗證

- 批次案例在memory與真MySQL各跑一次：
  - 同批Epic→子卡引用、缺title與引用不存在只讓該項失敗且不留半張卡。
  - 同requestId取回原結果、異內容409。
  - createOnly換新requestId補跑不重複也不更新。
  - 非createOnly缺revision逐項REVISION_CONFLICT。
  - 結構錯誤整批400、read Token 403。
  - 回應對照正式Schema。
- 隔離MySQL完整207通過、官方OpenAPI結構檢查與Pint通過。

## 限制

- 不是非同步工作佇列。50項在單一交易與Workspace鎖內完成，大量資料請分批。
- 逐項結果只在原requestId的receipt內，沒有另外的批次狀態查詢端點。
- 失敗項目要依error自行修正後重送。
