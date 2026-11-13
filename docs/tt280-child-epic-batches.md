# TT-280 父任務換Epic的子卡同步

父任務換Epic時, 原本會逐張子卡載入描述、留言與全部歷史, 再用完整卡片寫回。每卡60筆歷史、20筆留言的隔離MySQL資料, 100張子卡會產生1735次SQL

這次Service只產生實際Epic變更的版本與歷史事件, Repository每批最多100張, 讀取ID、revision、原Epic與下一個歷史位置, 同批更新Epic／revision／updated_at並追加事件。舊歷史、留言及其他欄位保留, 不逐批commit

## 資料存取與一致性

1. 呼叫端仍是原updateIssue, 只在父任務Epic實際改變時同步子卡；前端、Session／PAT契約與有界寫入回應未改
2. 子卡以內部ID穩定排序, 每批100張；SQL限定可信Workspace、Project及parent_issue_id
3. 同批更新帶各卡預期revision, 成功數不符由Service回CONFLICT, 外層交易整筆回滾
4. 不載整個Workspace或子卡活動JSON；新Repository與Service用建構子DI, 當次外鍵為readonly DTO, 不跨請求保存資格
5. 舊留言、關聯與歷史不重寫, 只插入本次歷史事件；沒有變更不增加版本或事件
6. 歷史尾端使用既有entity_type／entity_id／stream／position索引反向取1筆。MySQL相關子查詢必須把entity_id一併排序, 只有position倒序仍可能filesort。首批100張的EXPLAIN ANALYZE確認每張只讀1筆, 不是最後空批次計畫
7. 保留原User→Workspace鎖序與交易邊界、receipt及重播前資格重驗；第二批失敗、子卡版本衝突都驗整筆回滾。清空Epic、無變更、重播及無關卡不受影響均有memory／MySQL案例
8. 這張只處理子卡同步；父任務／目標Epic、Project及雙向關聯仍沿既有路徑, 不宣稱整個寫入服務已全面最佳化。後續指定TT-278／279仍未完成

## 實測

專用Compose tasktracker-codex-perf／MySQL8.4.13／tasktracker_test, 每卡60筆舊歷史及20筆留言。相同資料交錯舊新, 排除前2次暖機後各7次；每次原User→Workspace鎖序與交易一併量測, 最後回滾

| 子卡 | SQL舊→新 | 交易與Service中位數舊→新 |
| --- | --- | --- |
| 1 | 51→37 | 31.10→23.72ms |
| 20 | 374→37 | 130.40→26.10ms |
| 100 | 1735→38 | 522.25→33.25ms |

100張時Issue Model讀入203→3個, 留言查詢203→3次, 完整歷史查詢204→4次；留下的是父項與既有授權／通知等固定路徑。HTTP有界寫入投影各組新舊大小一致, 不因子卡活動成長。這是Service＋DB交易的本地實驗, 不含HTTP、receipt儲存或網路延遲, 不是正式p95

合成證據見[comparison](evidence/tt280-child-epic-comparison.json)與[100張首批計畫](evidence/tt280-child-epic-plan.json)。未新增migration或正式索引；曾在隔離庫比較涵蓋索引, 最後證實原索引即可, 實驗索引已移除

合併回歸56項2904assertions通過, 含memory／MySQL版本、原活動保留、批次回滾、公開寫入與容器解析, 詳記plans/TT-280/step.md；看板三尺寸真瀏覽器通過, 本卡不改畫面或baseline。Pint與共用Skill校驗均在交付前重跑

共用task-tracker-usage剛更新後, sources/canonical-files.json仍是舊雜湊。僅同步該一筆校驗值, 共用庫提交8377be1, 不改使用者規範正文
