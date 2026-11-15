# TT-368 任務計畫日期

- 預估 150 分鐘, startDate／endDate 使用可空 DATE, 與到期日分開
- 先完成 migration、共用 Fields 驗證、Repository 映射與詳細 Resource；Web／PAT 共用業務規則
- 編輯抽屜持有日期草稿, 詳細頁只顯示已存日期；列表不新增未使用的欄位
- 驗證單端、同日、閏年、順序、清除、PATCH 省略、歷史、revision／重播、租戶與 scope
- 前後畫面三尺寸與瀏覽器流程, SQLite／MySQL 及 OpenAPI 同步後提交

## 資料存取審查

詳細頁只多讀目前單一卡片的兩個日期, 無新增關聯查詢。列表與看板不預載排程資料, 篩選／分頁／穩定排序不變；不做 Season 2 排程畫面

## Skill 與元件分工

依共用工程、Laravel 分層、ECC、Vue 及 UI Skills；保留既有 JavaScript／script setup。IssuePanel 負責編輯草稿與欄位驗證, IssueDetail 顯示屬性, 純日期判斷放 domain/model, 不增加全域狀態
