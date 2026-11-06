---
name: laravel-architecture
description: Apply TaskTracker Laravel layer responsibilities, dependency direction, naming, DTO and Resource contracts, tenant-safe transactions, and Unit/Feature test decisions. Use with laravel-patterns for backend implementation, review, migrations, and test design.
---

# TaskTracker Laravel Architecture

後端工作先讀專案 [AGENTS.md](../../AGENTS.md)、[backend/AGENTS.md](../../backend/AGENTS.md) 與 [ECC laravel-patterns](../vendor/laravel-patterns/SKILL.md)，再套本補充。只有專案內副本生效，不自動讀共用目錄、安裝上游其他skills或執行hooks。

## 優先序與工作範圍

- 使用者當次要求、產品契約及授權／資料安全限制優先；架構與慣例以ECC為基礎，本skill只補足未規定的細節。與舊backend-development-standards衝突時採ECC，舊skill已退役，不載入雙套規則。
- 上游範例的Sanctum、REST、snake_case輸入、success envelope及DB方案不是本專案的遷移要求；保留Session／CSRF、RPC路由、camelCase API、ok/data、DomainError、revision／requestId及SQLite。變更契約要另有使用者授權與相容設計。
- 2026-11-06使用者先暫緩、後續已明確恢復前後端重構；只處理當輪規劃及授權範圍。安裝本skill本身仍不代表授權任意改寫或現有程式已全面合規，重構紀錄見docs/refactoring-2026-11-06.md。

## 開工與交付

1. 閱讀受影響入口、相近用例與測試，列出可信身分、permission／scope、輸入／回傳、交易與失敗時不得發生的副作用。
2. 用下表決定最少必要層，實作前確認依賴及交易owner。不得今天Controller查DB、明天才走Service。
3. 依 [層次與命名](references/layers-and-naming.md) 與 [契約、交易及測試](references/contracts-and-testing.md) 實作；讀取時不得套用角色名稱放行或跨租戶全表查詢。
4. 先取得受影響測試baseline，修改後跑完整受影響測試檔；跨層契約／共用邏輯依風險擴大。純skill／文件只檢查來源、連結、一致性與差異，不跑業務資料測試。
5. 依根AGENTS記錄計畫、決策、驗證與每階段本機commit，不自動push或部署。

## 層次選擇（必須按條件判斷）

| 情境 | 必要路徑 | 不要新增 |
| --- | --- | --- |
| 單Model、固定租戶範圍的簡單CRUD | Controller → 有輸入時FormRequest → Service → Eloquent Model → Resource | 無實際用途的Action／Repository／interface |
| 查詢有重複、複雜關聯／聚合，或需隔離可替換資料來源 | Controller → FormRequest → Service → Repository／Query → Model → Resource | 每個Model自動配一個Repository |
| 有獨立名稱、獨立失敗／交易邊界的單一用例 | Controller／Job → Action.handle → 必要Service／Repository／Model；HTTP輸入與輸出仍經Request／Resource | Service與Action互相原樣轉呼叫 |
| 多步驟跨用例流程 | Controller／Job → Service協調 → 必要的Action／Repository／Model | Controller承擔流程或讓Repository決定業務順序 |

FormRequest在Controller執行前做HTTP輸入驗證，不是Controller呼叫後才執行的下層。Resource是輸出邊界，也不是Model之後的資料查詢層。箭頭是責任與資料流，不強迫每條路徑產生相同數量的檔案。

Controller不得直接操作Eloquent／DB／Repository。Service或Action可依上表直接用Eloquent，已有符合用途的Repository則沿用；不得為跳層另建第二套資料入口。Repository只做資料存取，詳細回傳型別及例外見reference。
