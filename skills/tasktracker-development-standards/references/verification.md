# 驗證與交付門檻

## 按變更風險選驗證

- 純文件／skill：檢查規則一致性、來源對照、frontmatter、入口symlink、相對連結與diff，不額外建立業務測試、不重啟Docker或操作資料庫。
- 功能：驗證實際使用流程，按風險補permission／scope、作者限制、撤權、版本衝突、防重複、資料保存及失敗無副作用測試。Unit驗純規則，Feature驗HTTP／真DB，具體層次依[測試策略](../../laravel-architecture/references/contracts-and-testing.md)。
- 前後端契約／載入修改：前後端用相同API，真正隔離Laravel＋瀏覽器整合驗證；mock不能取代整合。只新增後端端點不能當作前端已切換；Network需有實際所需範圍、背景請求與活動按需證據。
- DI／PHPDoc：依本skill兩份專門reference驗證容器解析、可替換依賴、循環、初始化、跨租戶與長駐程序、型別及語意。全面重構依原任務建立完整掃描清單；至少增加另一個檢查流程核對生命週期／隔離／註解／例外，不要求一定使用多agent，可用獨立掃描、測試及第二輪差異審閱。

## 隔離與真實引擎

- 預設TestCase在bootstrap前強制putenv／SERVER／ENV及memory config，不只靠phpunit.xml。SQLite適合快速隔離回歸與歷史遷移；外部sentinel不得改動。
- MySQL資料型別、InnoDB交易／鎖、並行／死鎖、遷移必須用獨立測試帳號／schema與隔離Compose驗證；不能以memory SQLite取代真實引擎／遠端網路拓樸。
- 不對主要／遠端現有業務DB做seed、RefreshDatabase或破壞性測試；demo入口只允許local／testing，不以--force略過限制。不對共享Redis全庫flush。
- 隔離Docker helper必須覆寫com.docker.compose.project／service標籤，避免承襲正式服務標籤。測試命令／拓樸／資料量／快取冷暖與限制如實記錄。
- 查詢效能走[八項檢查](data-access.md)，量測分開SQL、rows、bytes、HTTP與瀏覽器；單次耗時不是p95，lab不是RUM。

## 交付

- 記錄改動目的、實際範圍、測試命令／結果及限制。未驗到或仍有P1就明列未完成／受阻，不把局部改善、格式化、安裝skill或一個commit說成整體驗收。
- 全庫DI／PHPDoc交付另報檔案數、替換new／locator數、例外、註解覆蓋與分母、MySQL及瀏覽器實測、未完成項目，不能只交「已整理」。
- 編譯、初始化資料、上傳、部署是不同動作，分別回報。未正式部署不得宣稱正式成功。
- 保留使用者修改、AGENTS、skills、規劃文件與資料，不以清理為由刪除。依根AGENTS更新產品紀錄／plans、卡片真實狀態，每階段驗證後本機commit；不自行push／amend／squash或擴張操作授權。
