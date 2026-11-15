# TaskTracker Bridge

私人ChatGPT Sites Adapter, 與Laravel核心分開。2026-11-15只完成TT-348平台能力初查, 尚未發布、安裝或連線正式TaskTracker

Sites識別保存在`.openai/hosting.json`, 後續沿用, 不重複建立Site。來源寫入憑證只留當次工作記憶體, 過期使用同一Site重新取得, 不存進本目錄

目前已驗：

- 當前帳號能建立Site, 角色owner, custom access只允許owner, 沒有群組、編輯者或外部訪客
- 原生環境設定支援`is_secret=true`, 合成值寫入後讀回不回明文；探針已移除
- 沒有PAT、登入資訊或正式業務資料, 沒有公開代理

TT-349接續8種唯讀工具與合成回應測試；首次發布後仍需驗MCP列舉、私人呼叫權限、執行時Secret隔離及固定HTTPS origin出站, 這些不在目前已驗項裡。完整紀錄見[能力檢查](../../docs/chatgpt-bridge-capability-check.md)
