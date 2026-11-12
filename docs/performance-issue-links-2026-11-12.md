# TT-221 任務關聯寫入限定查詢

2026-11-12。使用專案工程規範、Laravel Architecture／ECC 與 Addy 主診斷、ECC 量測流程。範圍為建立／更新卡片的關聯驗證，不更改 HTTP 契約或 Epic 結案規則。

## 實作與資料存取審查

1. 驗證只需指定 ID 的 type、Project、parent／Epic ID、反向關聯數及是否已關聯；使用 readonly IssueLinkData，不讀描述、留言或歷史。
2. HTTP 原有關聯上限 200 保留；父項與 Epic 加入同一批指定 ID 查詢。未知 ID 404、同 Workspace 跨 Project 400，保留原有拒絕語意。
3. 子任務／Epic 所屬卡片、未結案卡片與有效工時均由 SQL exists 判定；正常 API 不再 all(issues)／all(timeEntries)。Legacy 分支僅供歷史資料格式。
4. 父項改 Epic 時只同步該 Project 的實際子項，按內部主鍵 lazyById 每批 100 個 ID；逐筆讀寫需修改的卡片，保留 revision／history、交易原子性。成本仍與實際要修改的子項數成正比，並非固定成本。
5. 明確指定的關聯最多 202 個候選（父項、Epic、200 個關聯）；繼承父項 Epic 不在第一批時再補一筆。反向容量用 COUNT 子查詢，不載入其他端點的活動內容。
6. 調用仍在原本 HTTP 寫入交易、授權與鎖順序內；不新增 cache／背景輪詢，requestId／撤權／Session 與 afterCommit 維持。
7. 測試涵蓋無關 Project 成長、投影不含活動／描述、200 容量、跨 Project／不存在、父子／Epic／工時限制、雙向新增移除及 102 子項跨批同步。
8. 此卡不解決 TT-225 等其他查詢／初始化工作，也不宣稱全部效能改善完成。現有受影響卡片完整歷史讀寫仍由各自卡片追蹤。

## 驗證

- 環境：獨立 tasktracker-perf-test／原生 MySQL 8.4，tasktracker_test schema；預設 memory SQLite 僅為快速回歸，未操作業務庫 fixture。
- 修改前的同一 HTTP createIssue 測例：1 張無關卡片時 hydration 1；增加 120 張無關卡片及第一次建立的卡片後，hydration 122，穩定範圍斷言失敗。
- 修改後：無關資料成長不增加 hydration，正常建立不再掃全 Workspace；SQL 最小投影／200 容量測試通過。這是資料讀取範圍證據，不是 HTTP p95 或遠端 MySQL RTT 基準。
- 完整原生 MySQL 回歸 100 tests／4261 assertions；memory 83 passed／3774 assertions，17 個原生 MySQL case 按環境跳過。
- 最後新增跨批案例及 Pint 後，SQLite＋MySQL 受影響範圍 8 tests／112 assertions 通過。Pint 7 檔通過。
- API 回應格式不變，前端無需切換介面；後續 TT-183 會另跑真實 Chrome 既有互動回歸。
