# 工時月曆查詢（TT-223）

日期：2026-11-12。範圍只限專案工時月曆與當日明細；跨Project工時分析由TT-182負責。

## 變更

- 後端新增`time-calendar`月份摘要與`time-entries`當日分頁（契約見[分責查詢API](query-api-v1.md)）。Request只接受month／date／member／分頁；Service以`issue.read`＋`timeEntry.readTeam／readOwn`重驗，無團隊資格固定本人；Repository在SQL限定Workspace／Project／期間／未作廢，再依日、成員、卡片、Epic聚合。
- 前端TimeCalendar不再接收整包Workspace的issues／entries／members：月份／成員變更取消舊請求重讀摘要，點日期才讀當日分頁；成員與填報任務改用遠端有界候選（PagedSelect）。填報／作廢只重讀該月摘要與當日明細。工時頁加入scoped route，不再觸發getWorkspace。

## 八項資料存取審查

1. 呼叫端欄位：日曆格子只需日期／分鐘／筆數；報表只需成員名稱與分鐘、卡片識別／標題／預估／投入、Epic識別／標題／投入／預估；明細另取。
2. 上限與排序：days最多31列；members／issues／epics各上限100並以投入倒序＋內部ID穩定排序；明細20筆，created_at／id倒序。
3. SQL執行篩選與聚合，PHP不載入工時列。
4. 不讀整包Workspace；真實Chrome驗證bulkReads=0。
5. 當日明細點選才讀；卡片描述、活動不讀（測試檢查SQL無description／audit_events／comments）。
6. 頻率：進入頁面、換月／成員各一次摘要；點日期一次明細；背景30秒（失敗退避至120秒、jitter）只在面板關閉時重讀，隱藏分頁停止。摘要查詢固定4條聚合＋授權查詢；回應單次972 bytes（隔離環境人造資料，非p95）。
7. 測試：memory與獨立MySQL共用案例驗證其他Project／上月／作廢排除、Epic去重、成員篩選、20＋2分頁、日期／月份格式400、X-Workspace-ID 409、reader 403、撤權404；Chrome驗證請求參數、成員篩選、快速換月、填報後局部重讀與三尺寸。
8. 未完成／限制：time_entries尚無`(workspace_id, project_id, work_date)`索引，大型專案月份查詢依現有Project唯一索引掃描；索引與實測屬TT-182，未在本卡宣稱完成。清單上限100時介面提示只顯示前100項。舊整包RPC仍保留給其他未遷移頁面（工作區設定、專案成員等）。

## 既有測試狀態

`usability-fixes-check`（待辦tab斷言）與`time-analysis-check`（等待getWorkspace刷新）在本卡前已因先前分責改版過期，與月曆無關，未在本卡修改，需另行處理。
