# 來源參照、補送與回音辨識（TT-176）

2026-11-12。OpenAPI 0.9.0。使用規則以[公開契約](../backend/resources/public-api-contract.md)的「來源參照與稽核」及同步語意的回音段落為準。

## 邊界（2026-11-11已確認）

平台只保存可追溯的使用者、Token、來源參照與同步失敗資訊。外部可以附上自己的run或交付物參照。TaskTracker不內建Agent協作、分工、模型調用或編排；run生命週期只作為外部參照存進稽核。

## 決策（使用者授權先採Claude建議）

- **來源參照**：選填標頭`X-Client-Reference`，最多120字，格式受限。
  - 只存integration_audits.client_reference，不寫入卡片。
  - 不屬於receipt簽章，所以同requestId重送時不同參照也只回原結果，不新增稽核。
- **回音辨識**：稽核新增resource_revision，記錄寫入後的revision。
  - issue-changes的`lastWrite`只在(卡片, 目前revision)有對應的Token稽核時出現。
  - Web的Session寫入沒有Token稽核，會讓revision變大，lastWrite自然變成null，不會被誤判成回音。
  - 只回sameToken、sameUser；clientReference只對同一使用者回傳，不揭露他人的Token ID或名稱。
- **補送**：沿用RequestReplay，重播不進入寫入閉包，業務、通知、稽核都不重複。
- **同步失敗**：批次失敗項目保存在該requestId receipt的逐項結果，不另建失敗表。成功項目每項一筆稽核（原本整批一筆）。

## 資料與遷移

- migration 2026_11_12_160000：integration_audits新增可空的client_reference、resource_revision，以及索引(workspace_id, resource_id, resource_revision)。既有列保持null。
- IntegrationAuditReadRepository依(issue, revision)配對查詢，每頁最多100組，條件都在索引內。
- 現用主庫先私有備份，再migrate。

## 驗證

- 新增案例在memory與真MySQL各跑一次：
  - 參照寫入稽核、本人稽核清單可見。
  - 同requestId不同參照不新增紀錄。
  - 格式錯誤400且不寫入。
  - 同Token sameToken=true、同使用者的另一個Token sameToken=false。
  - Web修改後lastWrite為null。
  - 批次只為成功項目各記一筆。
- 測試替身IntegrationAudits::append的簽章同步更新。
- 隔離MySQL完整211通過；OpenAPI結構檢查與Pint通過。

## 限制

- 跨使用者（sameUser=false、參照不回傳）的情況依程式邏輯處理，測試涵蓋的是同一使用者的兩個Token。
- 單卡GET未附lastWrite，只在增量摘要提供。
- 稽核沒有TTL，沿用既有政策。
