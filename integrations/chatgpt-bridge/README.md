# TaskTracker Bridge

私人ChatGPT Sites Adapter, 與Laravel核心分開。2026-11-15只完成TT-348平台能力初查, 尚未發布、安裝或連線正式TaskTracker

Sites識別保存在`.openai/hosting.json`, 後續沿用, 不重複建立Site。來源寫入憑證只留當次工作記憶體, 過期使用同一Site重新取得, 不存進本目錄

目前已驗：

- 當前帳號能建立Site, 角色owner, custom access只允許owner, 沒有群組、編輯者或外部訪客
- 原生環境設定支援`is_secret=true`, 合成值寫入後讀回不回明文；探針已移除
- 沒有PAT、登入資訊或正式業務資料, 沒有公開代理

TT-349接續8種唯讀工具與合成回應測試；首次發布後仍需驗MCP列舉、私人呼叫權限、執行時Secret隔離及固定HTTPS origin出站, 這些不在目前已驗項裡。完整紀錄見[能力檢查](../../docs/chatgpt-bridge-capability-check.md)

## 本地開發與驗證

`npm test`執行合成HTTP與OpenAPI契約測試, `npm run build`產生單一Worker ESM, 沒有新增依賴。來源入口worker/index.js, 只輸出dist/server/index.js與dist/.openai/hosting.json, dist不進Git

工具：who_am_i、list_projects、search_issues、get_issue、list_issue_comments、list_boards、get_board、list_board_issues。get_issue的issue接受公開UUID或完整卡號, 其他資源接受公開UUID（board另接受default）；不接受內部數字ID或任意URL

Site伺服器設定：TASKTRACKER_OWNER_EMAIL為目前Site owner的已驗證信箱、TASKTRACKER_WORKSPACE_ID為專用PAT綁定的空間。TASKTRACKER_PAT必須標成Secret, 只能由使用者安全設定專用短效唯讀PAT, 不複用Agent憑證。設定值不放本檔或hosting manifest

每次工具呼叫核對Site可信身分及REST目前身分／read資格, 不跨請求快取資格或資料。單頁最多20筆, request最多16KiB、上游JSON最多256KiB、每次REST請求10秒, 拒絕轉址。一般操作2次REST, 卡號讀詳情3次, 不自動翻頁／輪詢／重試

TT-349已完成合成工具, 私人發布、安裝與正式唯讀驗收仍由TT-350執行, 尚未連線正式資料

2026-11-16 TT-399新增get_project、list_project_members及list_project_statuses, 使用正式Project詳細及有界候選API。合計11個唯讀工具, 每次重驗身分／工作區, 成員最多20筆、狀態最多20筆。發布與真實驗證結果留卡片

TT-351卡片寫入：create_issue、update_issue、comment_issue輸入project／issue與body。先不帶confirmation取得預覽, 展示完整對象及內容並請使用者確認後, 以相同參數附回confirmation執行。票據10分鐘有效且綁定本人、空間、工具與payload；不可更動內容或自行替換requestId。API錯誤不自動重試, 逾時後保留原參數與requestId, 先核對結果。此為Plugin工作流程, 不取代後端即時權限及版本檢查
