# TT-225 請求內ID對照與租戶查詢

2026-11-12，範圍為 RelationalRecords 的識別碼解析及實際參照預載。以 Addy 主診斷與 ECC 留存基準，遵守專案資料存取／Laravel 規範，不新增 Redis 資料或跨請求資格快取。

## 量測與定位

隔離 tasktracker-perf-test、MySQL 8.4／tasktracker_test；同一 Store 連讀同一張卡 8 次，該卡有 Project、負責人及一個關聯，另放 80 張無關卡片。

| 指標 | 修改前 | 修改後 |
| --- | ---: | ---: |
| SQL次數 | 65 | 44 |
| 外鍵ID對照查詢 | 24 | 3 |
| 對照範圍 | 重複 pluck 全租戶 | 僅實際參照ID、每批100 |

八次讀取的卡片、版本、狀態與子集合仍重讀。節省的是重複外鍵對照，不把所有SQL稱N+1。原本讀取側 Workspace ID 已在先前版本做單一 Store 暫存；本輪補上正常 scoped 寫入共用該ID，全球邀請仍按其可信目標查找。

原始去識別統計在 `.ecc/benchmarks/record-context-2026-11-12.json`；僅各1次同條件序列。原生MySQL SQL總時間原樣保留，單次前後時間落在波動範圍，不宣稱延遲加速／遠端SQL RTT或HTTP p95。確定收益為少21個往返及不再把無關ID載進PHP。

## 資料存取與生命週期審查

1. 主列／子列依既有操作指定種類與ID讀取；參照轉換只需內部主鍵、公開ID。成員顯示只按當次主列的user IDs讀 id/name/email，不再取整個Workspace所有User Model。
2. 參照查詢以 whereIn 限定且每批100；105個關聯的測試明確驗100＋5兩批。對照映射不依返回順序，卡片關聯／看板收錄仍保留原position順序。
3. get／all先收集當次主列與已預載子集合的必要外鍵，批次解析後才序列化，避免把全租戶pluck改成逐列SQL。寫入先批次準備所有明確參照，保留原FK及領域授權。
4. Store由伺服器當次Workspace建立；forWorkspace回新Store，不帶舊映射。只在此實例重用正向不可變ID，不存Model、角色、資格、狀態設定或revision；每次讀成員姓名／停用狀態及卡片內容均新鮮讀取。
5. put進入時清對照，在finally清除，包含失敗rollback；不讓半途插入映射殘留。Store不應跨HTTP／Queue工作或跨外層交易保存；既有Factory仍每次建立新實例，未註冊singleton／全域靜態cache。
6. 原始HTTP授權、User→Workspace鎖、revision、requestId／先授權再replay與afterCommit均不變；沒有新增輪詢或更動API DTO。真實看板前端可直接用現有接口，不需雙份業務實作。
7. 原始 all() 相容入口仍屬TT-180等卡退役範圍，本卡只修其中參照預載，不宣稱其主列已分頁。Schema檢查保留每Store一次；未以全程序靜態結果換取跨migration／測試schema錯誤，按需組裝初始化仍屬TT-233。
8. 無關卡片成長、參照whereIn／binding上限、105關聯、failed write、同Store讀到新revision／改名與disabledAt、跨Workspace拒絕皆有真DB斷言。其他未完成P1仍保留，不宣稱整體驗收完成。

## 驗證

- 完整獨立MySQL：106 tests／4379 assertions（新增最後跨批case前）；最後影響範圍SQLite＋MySQL 6 tests／134 assertions通過。
- 完整memory：87 passed／3848 assertions，21個原生MySQL測試依環境跳過；不將此結果當MySQL證據。
- Pint：5個影響PHP檔通過；最後測試檔再格式化並實際執行通過。
- Production Chrome 看板三尺寸：底卡／Epic／偏好／拖曳／雙擊／鍵盤／手機編輯通過，使用13029隔離服務；HTTP契約未改。
