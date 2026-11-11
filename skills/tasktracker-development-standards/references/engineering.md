# 共通工程與資料安全

## 程式與資料邊界

- 前後端分離；Vue元件處理呈現及互動狀態，只經frontend/src/api呼叫JSON API。後端負責可信身分、授權、驗證及儲存，不在前端直接操作資料庫或匯入PHP服務。
- JavaScript使用2格縮排、單引號、分號，優先const；函式／變數camelCase、常數UPPER_SNAKE_CASE。PHP採4格縮排與現有Pint慣例，類別、Request／DTO／Resource／Repository命名依[Laravel分層與命名](../../laravel-architecture/references/layers-and-naming.md)。
- Vue保持Composition API／script setup；現有JavaScript不因套用skill全庫改TypeScript。新增功能／明確遷移優先考慮TypeScript，不能據此自行換框架、Router或狀態套件。
- 僅傳可序列化資料：時間戳ISO 8601、日曆日期YYYY-MM-DD，工作日Asia/Taipei；不可把Date、框架物件、DB Model、Range或Sheet直接當傳輸契約。
- 明確原生型別與回傳型別，複雜條件用具名變數／方法；避免密集單行及隱藏流程。業務JSON經Resource或既有ApiResponse，依賴與命名不為多一層而多一層。
- 維護lockfile，實際修改依賴／環境前查證適用版本，不照抄參考專案；不因規範整理自行升版、安裝分析器或formatter。

## 授權、交易與同步

- 角色只是permission映射。有效permission ID＋Workspace／Project scope＋資源／作者條件才是授權依據；roleId只查映射，不能以角色名稱或ID直接放行。隱藏按鈕或前端自稱身分不能代替後端驗證。
- Actor、目前Workspace與Session由伺服器重驗；保留Session／CSRF、X-Workspace-ID跨分頁檢查與必要Session blocking。讀取後資格可能改變，寫入交易及讀requestId重試結果前仍須重新授權。
- MySQL寫入先按既有順序鎖User→Workspace穩定列，再驗資格、版本與規則；寫入使用穩定識別ID，保留revision、requestId、防重複、原子rollback與作者限制，不依賴SQLite全庫寫鎖。
- 保留明確transaction owner與afterCommit副作用；Reverb／Queue不得提早送出未commit結果。工作區／操作者不可跨請求或長駐程序混用。
- 非同步呼叫要處理失敗、重送、重複提交、取消及過期回應；切換Workspace清掉舊scope資料。背景刷新不能偷換未儲存草稿的基準revision，撤權時不得繼續顯示／寫入失效資源。
- API可依已授權的資料分責改版／退役，不永久保留整包Workspace作相容條件；同步呼叫端、測試與文件。只做DI或PHPDoc時則不暗中改外部路由、欄位、envelope或業務結果；必要變更另行明確設計。

## 安全與使用者內容

- 使用者內容預設文字呈現；Markdown／HTML視為不可信輸入，處理XSS與連結等安全邊界。
- 憑證、Cookie、Session、私有設定、資料庫與備份不得進Git、前端bundle、image、卡片或日誌；不要輸出秘密檔內容。
- 不以本輪重構刪除歷史、放寬租戶／作者限制或清空既有資料。SQLite歷史來源、舊volume與外部資料遷移狀態依AGENTS，不直接回切舊庫覆蓋新進度。
- 共享Redis用環境／服務namespace與完整scope key；tags只作群組失效，不代替prefix／ACL。禁止FLUSHDB、FLUSHALL、Cache::flush及cache:clear。

## 介面文案與歷史GAS邊界

- 只保留標題、欄位／操作、實際資料、必要狀態及錯誤／結果／確認；不新增情緒性標語、長篇功能介紹或常駐操作教學。使用者輸入的介紹是業務資料，不能當系統文案刪掉；操作手冊另依明確指示。
- GAS為歷史方向，不恢復開發或產.gs。若當次另有明確GAS工作授權，才適用：共用全域避免名稱衝突／載入即寫入；google.script.run處理非同步／重送／過期；不假定以部署者執行可取得訪客Email；跨工作表寫入明確定義部分失敗。未有授權時不能因本段生成GAS產物。
