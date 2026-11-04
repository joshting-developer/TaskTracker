---
name: backend-development-standards
description: Implement or review TaskTracker Laravel/PHP backend code and tests using project coding, architecture, API, concurrency, database and process-lifecycle standards. Use for backend changes and test design.
---

# TaskTracker Backend Development Standards

依共用規範建立的專案副本，來源與調整見 [來源清單](../backend-standards-sources.json)。後端開發先讀 [backend/AGENTS.md](../../backend/AGENTS.md) 及根規範；本skill不授權原任務以外的重構、依賴升級、部署或外部操作。

## 開發流程

1. 先讀相近Controller、Service、Repository、Model、migration與測試，確認目前契約與慣例。
2. 拆清API輸入／輸出、授權、資料一致性、副作用及驗收條件。
3. 只建立實際需要的層；流程、條件與命名必須讓人能理解，不用密集單行壓短程式。
4. 功能修改先跑受影響的最小測試取得baseline，完成後跑完整受影響測試檔；共用邏輯依風險擴大驗證。純規範／文件修改檢查內容、連結與差異即可。
5. 檢查API文件、migration、log、設定及Docker啟動／升級流程是否受影響，再依根規範提交。

## TaskTracker適用邊界

- 權限以有效permission ID＋Workspace／Project scope＋資源條件判斷；角色僅查權限映射。操作者與權限由後端重新取得，不能比較角色名稱放行；重試快取亦須先授權。
- 保留既有JSON API DTO、DomainError錯誤碼／HTTP語意、revision與requestId；CLM錯誤碼與translation key不搬入產品。診斷細節只進受控log，不能回傳SQL、憑證或exception原文。
- 保留SQLite記憶體測試隔離及TestCase在bootstrap前的環境覆寫，不因reference中的DatabaseTransactions範例改用業務資料庫。
- 新增／修改class與function依coding reference補中文docblock；既有原型不視為已符合規範，也不因引入skill整批重排、改名或重構。使用者已暫緩後端架構整理。
- CLM的Kibana、Report／PDF、安裝包與Carbon macro名稱僅為來源專案例子；TaskTracker依實際相依判斷，使用既有ISO時間／Asia/Taipei工作日語意，不新增不存在的系統。

## 按需讀取

- coding style、中文docblock、migration與Unit／Feature測試：[coding-style-and-tests.md](references/coding-style-and-tests.md)
- Controller／Service／Repository／Transformer責任：[architecture.md](references/architecture.md)
- 批次API、逐筆授權與部分成功：[batch-api-review.md](references/batch-api-review.md)
- 對外錯誤與過期資料／交易契約：[api-contract-and-concurrency.md](references/api-contract-and-concurrency.md)
- fixture、主鍵與shared state：[testing-reliability.md](references/testing-reliability.md)
- command／worker／browser等外部程序：[external-process-lifecycle.md](references/external-process-lifecycle.md)

同一細節只在對應reference維護，依當次工作選讀，不預載全部規則。
