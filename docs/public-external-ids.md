# 外部識別與upsert（TT-171）

2026-11-12。正式OpenAPI 0.6.0：13 paths、18 operations。新增`getPublicIssueByExternalId`與`upsertPublicIssueByExternalId`，共用路徑`/api/public/v1/projects/{project}/issues/external/{source}/{externalId}`。使用規則以[公開契約](../backend/resources/public-api-contract.md)的「外部識別與upsert」為準，本文記錄設計、驗證與限制。

## 已確認決策

- 使用者確認：已綁定卡片的upsert**必須帶expectedRevision**，不提供無版本覆蓋。
- 使用者確認：現用本地主庫先備份，再執行新增表migration。
- 其餘細節依Claude建議（使用者授權先採建議）：獨立對照表、路徑式識別、識別區分大小寫、綁定不可解除或改綁。

## 設計

- **資料**：新增`issue_external_refs`，存workspace／project／issue內部鍵、source、external_id。
  - 唯一索引：(project_id, source, external_id)，以及(issue_id, source)。
  - MySQL欄位用utf8mb4_bin，所以ABC與abc是不同識別。
  - 刪除卡片或Project時連帶刪除綁定；Workspace用restrict。
  - 不在issues表加欄位，舊陣列存取層與Session DTO都不受影響。
- **分層**：
  - PublicIssueExternalController只處理HTTP。
  - Integrations/IssueExternalReferences負責規則：Project permission、建立或更新、expectedRevision必填。
  - IssueExternalRefRepository只做限定scope的查詢與新增。
  - 全部透過建構子注入，符合TT-262的嚴格分層。卡片的建立與更新共用WorkspaceService.executePublicIssue，不複製業務。
- **交易與冪等**：
  - 沿BearerTokenSession.writeOnce：User→Workspace鎖後重驗Token、Scope與Project issue.write，之後才讀receipt。
  - receipt操作名為public.v1.upsertIssue，簽章包含路徑上的識別。
  - 同Workspace的寫入由鎖序列化，唯一索引作為最後防線。
  - 首次寫入才追加integration_audits，重播時不重複。
- **回應**：有界的id、projectId、identifier、revision、externalSource、externalId、created；不回整張卡。GET同路徑回完整單卡。

## 驗證

- 新增PublicExternalRefCases，在memory與真MySQL各跑一次：
  - 只建一張卡、缺少或過期revision回409、requestId重播與異內容409、省略欄位保留、稽核不重複。
  - 大小寫區分、跨Project不碰撞、read Token只能讀、跨Workspace拒絕、格式錯誤400、未綁定404。
  - 所有回應都對照正式OpenAPI Schema。
- 隔離MySQL完整203通過；官方OpenAPI離線結構檢查通過；Pint通過。
- RelationalStorageTest與TodoTest會直接呼叫舊migration的down()。測試改成先移除新表，正常rollback本來就會先撤後續migration，舊migration不改。

## 限制與後續

- externalId不支援斜線等字元，客戶端需自行對應或雜湊。
- 綁定無法解除或改綁，也沒有批次upsert。批次與逐項結果屬TT-172，全量／增量與刪除語意屬TT-173。
- 單卡GET與issue-changes目前不附外部識別欄位；TT-173同步需要時再按需加入。
